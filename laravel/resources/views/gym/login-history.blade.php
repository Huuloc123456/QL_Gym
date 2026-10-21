@extends('layouts.app')
@section('title', 'Lịch sử đăng nhập')
@section('content')
<section class="gym-container py-10"><h1 class="gym-title">Lịch sử đăng nhập</h1><div class="mt-6 overflow-x-auto rounded-xl border border-slate-200" tabindex="0" aria-label="Lịch sử đăng nhập"><table class="gym-table"><thead><tr><th>Thời gian</th><th>Địa chỉ IP</th><th>Kết quả</th></tr></thead><tbody>@forelse ($history as $login)<tr><td>{{ $login->LOGINTIME }}</td><td>{{ $login->IPADDRESS }}</td><td>{{ $login->STATUS ? 'Thành công' : 'Chưa thành công' }}</td></tr>@empty<tr><td colspan="3">Chưa có lịch sử.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $history->links() }}</div></section>
@endsection
