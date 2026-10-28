@if (auth()->user()->role === 'manager' && $membership->TRANGTHAI === 'Còn hiệu lực')
    <form class="flex min-w-64 flex-wrap items-end gap-2" method="POST" action="{{ route('manager.memberships.end', $membership->MADKGT) }}">
        @csrf
        <label class="sr-only" for="membership-end-reason-{{ $membership->MADKGT }}">Lý do chấm dứt gói</label>
        <input class="gym-input min-w-48 text-sm" id="membership-end-reason-{{ $membership->MADKGT }}" name="reason" placeholder="Lý do chấm dứt gói" minlength="5" maxlength="255" required>
        <button class="text-sm font-semibold text-red-700 underline" type="submit">Chấm dứt gói</button>
    </form>
@else
    —
@endif
