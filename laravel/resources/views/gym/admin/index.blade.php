@extends('layouts.app')
@section('title', $title)
@section('content')
<section class="gym-container py-10">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-bold uppercase tracking-[0.16em] text-gym-blue">Khu vực vận hành</p><h1 class="gym-title mt-2">{{ $staffMode ? 'Quản lý sản phẩm' : $title }}</h1>@if ($staffMode)<p class="mt-2 max-w-2xl text-slate-600">Tạo và cập nhật thông tin, hình ảnh cùng số lượng thực tế. Tồn kho đang giữ cho đơn chờ được hiển thị để tránh chỉnh thiếu hàng.</p>@endif</div>
        <a class="gym-btn-primary" href="{{ $staffMode ? route('staff.products.create') : route('gym.admin.create', $resource) }}">{{ $staffMode ? 'Thêm sản phẩm' : 'Thêm mới' }}</a>
    </header>
    @if (in_array($resource, ['employees', 'positions'], true))
        <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Chức vụ PT và HLV lớp là dữ liệu lịch sử. The Gym hiện không nhận đặt PT hoặc lớp học nhóm; các chức vụ này không được dùng để phân công mới.</p>
    @endif
    <form class="mt-6 flex gap-3" method="GET"><input class="gym-input max-w-md" name="q" value="{{ request('q') }}" placeholder="Tìm theo tên" aria-label="Tìm theo tên"><button class="gym-btn-outline" type="submit">Tìm</button></form>
    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Danh sách {{ $title }}">
        <table class="gym-table"><thead><tr><th>Mã</th>@foreach ($fields as [$label, $rules])<th>{{ $label }}</th>@endforeach @if ($staffMode)<th>Đang giữ</th><th>Còn bán</th>@endif<th>Thao tác</th></tr></thead>
            <tbody>@forelse ($rows as $row)<tr><td>{{ $row->$key }}</td>@foreach ($fields as $field => [$label, $rules])<td class="max-w-64"><span title="{{ $row->$field }}">{{ \Illuminate\Support\Str::limit($row->$field ?? '—', 65) }}</span></td>@endforeach @if ($staffMode)<td>{{ (int) $row->reserved_quantity }}</td><td>{{ max(0, (int) $row->SOLUONGTON - (int) $row->reserved_quantity) }}</td>@endif<td class="whitespace-nowrap">@if ($resource === 'customers')<a class="font-semibold text-gym-blue underline" href="{{ route('gym.admin.customers.show', $row->$key) }}">Hồ sơ</a><span class="mx-1 text-slate-400">·</span>@endif<a class="font-semibold text-gym-blue underline" href="{{ $staffMode ? route('staff.products.edit', $row->$key) : route('gym.admin.edit', [$resource, $row->$key]) }}">Chỉnh sửa</a></td></tr>@empty<tr><td colspan="{{ count($fields) + ($staffMode ? 4 : 2) }}">Chưa có dữ liệu phù hợp.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-6">{{ $rows->links() }}</div>
</section>
@endsection
