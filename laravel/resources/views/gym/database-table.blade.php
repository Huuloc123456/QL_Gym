@extends('layouts.app')
@section('title', 'Dữ liệu '.$table)
@section('content')
<section class="gym-container py-10"><a class="text-sm text-gym-blue underline" href="{{ route('gym.database.index') }}">← Quản trị CSDL</a><h1 class="gym-title mt-4">{{ $table }}</h1><p class="mt-3 text-sm text-slate-500">Dữ liệu chỉ xem. Các thay đổi được thực hiện qua màn hình nghiệp vụ tương ứng.</p><div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Bảng {{ $table }}"><table class="gym-table"><thead><tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>@forelse ($rows as $row)<tr>@foreach ($columns as $column)<td class="max-w-64 break-words">{{ \Illuminate\Support\Str::limit((string) ($row->$column ?? '—'), 150) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}">Chưa có dữ liệu.</td></tr>@endforelse</tbody></table></div><div class="mt-6">{{ $rows->links() }}</div></section>
@endsection
