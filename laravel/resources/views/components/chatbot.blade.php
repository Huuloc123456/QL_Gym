<div data-gym-chatbot class="fixed bottom-5 right-4 z-[60] sm:bottom-6 sm:right-6">
    <section
        id="gymChatPanel"
        class="gym-chat-panel fixed inset-x-3 bottom-3 flex h-auto max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:absolute sm:inset-auto sm:bottom-16 sm:right-0 sm:max-h-[min(34rem,calc(100dvh-6rem))] sm:w-[22.5rem]"
        data-chat-panel
        aria-labelledby="gymChatTitle"
        aria-modal="false"
        role="dialog"
        hidden
    >
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-full bg-sky-50 text-gym-blue" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-3 2v-6.5a7.5 7.5 0 1 1 16-3Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <h2 id="gymChatTitle" class="text-base font-bold text-gym-ink">Trợ lý The Gym</h2>
            </div>
            <button class="gym-chat-close" type="button" data-chat-close aria-label="Đóng cửa sổ trò chuyện">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
            </button>
        </header>

        <div class="gym-chat-messages min-h-24 max-h-[45dvh] space-y-3 overflow-y-auto bg-gym-paper px-3 py-4 sm:max-h-72 sm:px-4" data-chat-messages role="log" aria-live="polite" aria-relevant="additions text">
            <article class="gym-chat-message gym-chat-message--assistant">
                <p>Chào bạn! Mình hỗ trợ tra cứu gói tập, sản phẩm, đơn hàng và thông tin công khai của The Gym.</p>
            </article>
        </div>

        <form class="border-t border-slate-200 bg-white px-3 pb-3 pt-2 sm:px-4" action="{{ route('chatbot.message') }}" method="POST" data-chat-form>
            @csrf
            <label class="sr-only" for="gymChatMessage">Nhập câu hỏi cho The Gym</label>
            <div class="flex items-end gap-2 rounded-xl border border-slate-300 bg-white p-1.5 transition focus-within:border-gym-blue focus-within:ring-2 focus-within:ring-sky-100">
                <textarea id="gymChatMessage" class="max-h-28 min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm leading-5 text-gym-ink outline-none placeholder:text-slate-400 focus:ring-0" name="message" rows="1" maxlength="500" placeholder="Nhập câu hỏi…" data-chat-input></textarea>
                <button class="gym-chat-send" type="submit" data-chat-send disabled aria-label="Gửi câu hỏi">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="2"><path d="M12 19V5m-7 7 7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <div class="mt-1.5 text-[0.68rem] text-slate-500">
                <span data-chat-status aria-live="polite">Không gửi mật khẩu hoặc mã OTP.</span>
                <span class="ml-2">Enter để gửi · Shift+Enter xuống dòng</span>
            </div>
        </form>
    </section>

    <button
        class="gym-chat-launcher inline-flex min-h-12 items-center gap-2.5 rounded-full bg-gym-blue px-4 py-2 text-white transition-colors hover:bg-gym-blue-deep focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gym-blue max-sm:size-11 max-sm:min-h-11 max-sm:justify-center max-sm:gap-0 max-sm:p-0"
        type="button"
        data-chat-open
        aria-label="Mở trợ lý The Gym"
        aria-controls="gymChatPanel"
        aria-expanded="false"
    >
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-5" stroke="currentColor" stroke-width="1.8"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-3 2v-6.5a7.5 7.5 0 1 1 16-3Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="text-sm font-semibold max-sm:sr-only">Hỏi The Gym</span>
    </button>
</div>
