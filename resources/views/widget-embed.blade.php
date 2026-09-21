{{-- In-app assistant (Chatsaas) — identity minted server-side, never in the browser. --}}
@if(config('chatsaas.widget_key'))
<script>
    window.LiveChatConfig = {
        widgetKey: "{{ config('chatsaas.widget_key') }}",
        apiEndpoint: "{{ config('chatsaas.api_endpoint') }}",
        userToken: "{{ \Chatsaas\LaravelWidget\AssistantIdentity::tokenFor(auth()->user()) }}",
        // Relative on purpose: same origin/port as the current page, so the session cookie
        // is sent. route() would use APP_URL, which can differ, and drop the cookie.
        getUserToken: () => fetch("{{ route('chatsaas.chat-token') }}").then(r => r.text()),
    };
    window.chatConfig = { aiOnly: true, language: "{{ app()->getLocale() }}" };
</script>
<script src="{{ config('chatsaas.widget_js') }}" async></script>
@endif
