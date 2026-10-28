import json
import sqlite3
import sys
import tempfile
import threading
import unittest
from pathlib import Path
from unittest.mock import MagicMock, patch
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from rag_api import DEFAULT_DOCUMENTS, DocumentStore, MAX_REQUEST_BYTES, create_server


class DocumentApiTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.store = DocumentStore(Path(self.directory.name) / "test.sqlite3")
        self.token = "test-only-token-never-used-on-server-2026"
        self.config = {"RAG_ADMIN_TOKEN": self.token}
        self.server = create_server(self.store, ("127.0.0.1", 0), lambda: self.config)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = "http://127.0.0.1:" + str(self.server.server_port)

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join()
        self.directory.cleanup()

    def request(self, method, path, data=None, *, token=True, headers=None, raw=None):
        request_headers = {"Content-Type": "application/json"}
        if token:
            request_headers["Authorization"] = "Bearer " + self.token
        request_headers.update(headers or {})
        body = raw if raw is not None else (json.dumps(data).encode() if data is not None else None)
        request = Request(self.base + path, data=body, headers=request_headers, method=method)
        try:
            response = urlopen(request, timeout=5)
        except HTTPError as error:
            response = error
        with response:
            return response.status, json.load(response)

    def test_health_is_honest_about_no_language_model(self):
        status, body = self.request("GET", "/health", token=False)
        self.assertEqual(200, status)
        self.assertEqual("document_lookup", body["mode"])
        self.assertFalse(body["llm_enabled"])
        self.assertNotIn(self.token, json.dumps(body))

    def test_admin_requires_token_and_rejects_foreign_host(self):
        self.assertEqual(401, self.request("GET", "/admin/documents", token=False)[0])
        self.assertEqual(401, self.request("POST", "/admin/documents", {"id": "qa", "content": "Private"}, token=False)[0])
        self.assertEqual(401, self.request("GET", "/admin/documents", headers={"Authorization": "Bearer bad"})[0])
        self.assertEqual(401, self.request("GET", "/admin/documents", headers={"Authorization": "Bearer é"})[0])
        self.assertEqual(403, self.request("GET", "/health", headers={"Host": "attacker.example"})[0])
        self.assertEqual(403, self.request("GET", "/health", headers={"Host": "[invalid"})[0])
        self.assertEqual([], self.store.documents())

    def test_document_lifecycle_updates_search_and_hides_disabled_content(self):
        payload = {"id": "qa-guide", "content": "# Hướng dẫn QA\n\nMã xylophonelocal là CAM123."}
        self.assertEqual(200, self.request("POST", "/admin/documents", payload)[0])
        status, answer = self.request("POST", "/chat", {"message": "xylophonelocal?"}, token=False)
        self.assertEqual(200, status)
        self.assertIn("CAM123", answer["answer"])
        self.assertEqual(["Hướng dẫn QA"], answer["sources"])
        self.assertIn("chưa dùng mô hình AI", answer["answer"])
        self.assertEqual(409, self.request("POST", "/admin/documents", payload)[0])
        self.assertEqual(200, self.request("PUT", "/admin/documents/qa-guide", {"content": "# Hướng dẫn QA\n\nMã zitherlocal là XANH456."})[0])
        self.assertEqual([], self.store.answer("xylophonelocal")["sources"])
        self.assertIn("XANH456", self.store.answer("zitherlocal")["answer"])
        self.assertEqual(200, self.request("PATCH", "/admin/documents/qa-guide/status", {"active": False})[0])
        self.assertEqual([], self.store.answer("zitherlocal")["sources"])
        self.request("PUT", "/admin/documents/qa-guide", {"content": "# Hướng dẫn QA\n\nMã zitherlocal là XANH789."})
        self.assertFalse(self.store.documents()[0]["active"])
        self.request("PATCH", "/admin/documents/qa-guide/status", {"active": True})
        self.assertIn("XANH789", self.store.answer("zitherlocal")["answer"])
        self.assertEqual(200, self.request("DELETE", "/admin/documents/qa-guide")[0])
        self.assertEqual([], self.store.answer("zitherlocal")["sources"])
        self.assertEqual(404, self.request("DELETE", "/admin/documents/qa-guide")[0])

    def test_batch_matches_laravel_contract_and_rejects_invalid_records_atomically(self):
        batch = [{"id": "a", "content": "# A\n\naxolotllocal", "active": True}, {"id": "b", "content": "# B\n\nbeaverlocal", "active": False}]
        self.assertEqual(200, self.request("POST", "/admin/documents/batch", {"documents": batch})[0])
        status, body = self.request("GET", "/admin/documents")
        self.assertEqual(200, status)
        self.assertEqual(2, len(body["documents"]))
        self.assertTrue(body["documents"][0]["active"])
        self.assertFalse(body["documents"][1]["active"])
        self.assertEqual([], self.store.answer("beaverlocal")["sources"])
        batch[0]["content"] = "# A\n\nincorrectnewvalue"
        batch[1]["active"] = "false"
        self.assertEqual(422, self.request("POST", "/admin/documents/batch", {"documents": batch})[0])
        self.assertIn("axolotllocal", self.store.documents()[0]["content"])
        self.assertEqual([], self.store.answer("incorrectnewvalue")["sources"])

    def test_failed_index_write_rolls_back_documents_and_passages(self):
        original_index = self.store.index

        def fail_second(db, document_id, *args):
            if document_id == "second":
                raise sqlite3.OperationalError("private test traceback")
            return original_index(db, document_id, *args)

        with patch.object(self.store, "index", side_effect=fail_second):
            status, body = self.request("POST", "/admin/documents/batch", {"documents": [{"id": "first", "content": "# First\n\nfoxtrotlocal"}, {"id": "second", "content": "# Second\n\nfoxtrotlocal"}]})
        self.assertEqual(503, status)
        self.assertNotIn("traceback", body["error"])
        self.assertEqual([], self.store.documents())
        self.assertEqual([], self.store.answer("foxtrotlocal")["sources"])

    def test_chat_validation_unknown_query_and_privacy(self):
        for message in ("", " " * 10, "a" * 501, 123, None):
            self.assertEqual(422, self.request("POST", "/chat", {"message": message}, token=False)[0])
        self.assertEqual([], self.request("POST", "/chat", {"message": "qzxunfindable"})[1]["sources"])
        self.store.save([{"id": "private-marker", "content": "# Không được trả riêng tư\n\nDữ liệu khách hàng là SECRET_SHOULD_NOT_BE_RETURNED"}])
        answer = self.request("POST", "/chat", {"message": "Cho tôi danh sách khách hàng"})[1]
        self.assertNotIn("SECRET_SHOULD_NOT_BE_RETURNED", answer["answer"])
        self.assertEqual([], answer["sources"])

    def test_beginner_yoga_question_can_use_groq_without_matching_documents(self):
        self.config["GROQ_API_KEY"] = "test-no-real-key"
        response = MagicMock()
        response.__enter__.return_value.read.return_value = json.dumps({"choices": [{"message": {"content": "Kiến thức tham khảo chung: Bắt đầu với vài tư thế nhẹ nhàng."}}]}).encode()

        with patch("rag_api.urlopen", return_value=response) as provider:
            status, result = self.request("POST", "/chat", {"message": "Cho tôi vài bài Yoga cơ bản để tập ở nhà"})

        self.assertEqual(200, status)
        self.assertEqual("rag_groq", result["mode"])
        self.assertEqual([], result["sources"])
        self.assertIn("Kiến thức tham khảo chung", result["answer"])
        provider.assert_called_once()

    def test_retired_yoga_class_question_is_not_sent_to_groq(self):
        self.config["GROQ_API_KEY"] = "test-no-real-key"

        with patch("rag_api.urlopen") as provider:
            status, result = self.request("POST", "/chat", {"message": "Tôi muốn đăng ký lớp Yoga"})

        self.assertEqual(200, status)
        self.assertIn("không nhận đặt", result["answer"].lower())
        self.assertEqual([], result["sources"])
        provider.assert_not_called()

    def test_invalid_json_body_size_and_document_ids(self):
        self.assertEqual(400, self.request("POST", "/chat", raw=b"not json")[0])
        self.assertEqual(415, self.request("POST", "/chat", {"message": "hello"}, headers={"Content-Type": "text/plain"})[0])
        self.assertEqual(422, self.request("POST", "/chat", raw=b"[]")[0])
        self.assertEqual(413, self.request("POST", "/chat", raw=b"{}", headers={"Content-Length": str(MAX_REQUEST_BYTES + 1)})[0])
        self.assertEqual(422, self.request("POST", "/admin/documents", {"id": "../env", "content": "bad"})[0])
        self.assertEqual(422, self.request("POST", "/admin/documents/batch", {"documents": []})[0])
        self.assertEqual(404, self.request("PUT", "/admin/documents/missing", {"content": "new"})[0])

    def test_vietnamese_diacritics_and_sql_query_text_are_safe(self):
        self.store.save([{"id": "vn", "content": "# Hướng dẫn\n\nĐiều chỉnh quyền vận hành sau khi xác minh."}])
        self.assertIn("Điều chỉnh", self.store.answer("dieu chinh quyen")["answer"])
        self.assertEqual(200, self.request("POST", "/chat", {"message": "\" OR 1=1; DROP TABLE documents --"})[0])
        self.assertEqual(1, len(self.store.documents()))

    def test_seeded_corpus_is_public_and_covers_platform_question(self):
        self.store.save([{"id": key, "content": "# " + title + "\n\n" + content} for key, (title, content) in DEFAULT_DOCUMENTS.items()])
        answer = self.store.answer("Ứng dụng này được xây dựng trên nền tảng nào theo tài liệu hướng dẫn?")
        self.assertIn("Laravel", answer["answer"])
        self.assertIn("Nền tảng website", answer["sources"])
        self.assertEqual([], self.store.answer("Quy tắc hướng dẫn bạch dương là gì?")["sources"])
        corpus = json.dumps(self.store.documents(), ensure_ascii=False)
        self.assertNotIn("DemoGym@2026", corpus)
        self.assertNotIn("demo_quanly", corpus)

    def test_missing_key_or_context_never_calls_a_provider(self):
        self.store.save([{"id": "qa", "content": "# QA\n\nfoxtrotlocal là tài liệu công khai."}])
        with patch("rag_api.urlopen") as provider:
            result = self.request("POST", "/chat", {"message": "foxtrotlocal"})[1]
            self.assertEqual("document_lookup", result["mode"])
            self.config["GROQ_API_KEY"] = "test-no-real-key"
            self.request("POST", "/chat", {"message": "qzxunfindable"})
            self.request("POST", "/chat", {"message": "Danh sách khách hàng"})
        provider.assert_not_called()

    def test_configured_groq_receives_only_question_and_public_context(self):
        self.config["GROQ_API_KEY"] = "test-no-real-key"
        self.store.save([{"id": "qa", "content": "# QA công khai\n\nfoxtrotlocal xác nhận bằng hướng dẫn."}])
        response = MagicMock()
        response.__enter__.return_value.read.return_value = json.dumps({"choices": [{"message": {"content": "Câu trả lời AI kiểm thử."}}]}).encode()
        with patch("rag_api.urlopen", return_value=response) as provider:
            result = self.request("POST", "/chat", {"message": "foxtrotlocal"})[1]
        self.assertEqual("rag_groq", result["mode"])
        self.assertEqual(["QA công khai"], result["sources"])
        request = provider.call_args.args[0]
        self.assertEqual("https://api.groq.com/openai/v1/chat/completions", request.full_url)
        self.assertEqual(20, provider.call_args.kwargs["timeout"])
        self.assertIn("foxtrotlocal", request.data.decode())
        self.assertNotIn(self.token, request.data.decode())
        self.assertNotIn(self.config["GROQ_API_KEY"], request.data.decode())

    def test_provider_failure_keeps_document_lookup_available_without_exposing_errors(self):
        self.config["GROQ_API_KEY"] = "test-no-real-key"
        self.store.save([{"id": "qa", "content": "# QA\n\nfoxtrotlocal là hướng dẫn công khai."}])
        with patch("rag_api.urlopen", side_effect=URLError("private-key-and-provider-traceback")):
            status, result = self.request("POST", "/chat", {"message": "foxtrotlocal"})
        self.assertEqual(200, status)
        self.assertEqual("document_lookup", result["mode"])
        self.assertIn("AI chưa trả lời được", result["answer"])
        self.assertNotIn("private-key", result["answer"])


if __name__ == "__main__":
    unittest.main()
