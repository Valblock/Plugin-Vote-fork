@if($ipv6compatibility)
    <script src="https://cdn.ipv6-adapter.com/v1/api.js" async defer></script>
@endif

<script src="{{ plugin_asset('vote', 'js/vote.js?v4') }}" defer></script>
@auth
    <script>
        window.username = '{{ $user->name }}';
    </script>
@else
    @if($guestName ?? null)
        <script>
            window.voteGuestName = @json($guestName);
        </script>
    @endif
@endauth
