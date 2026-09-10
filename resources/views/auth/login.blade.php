<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign in — Server Pulse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#0B0E14] text-[#E6E8EB] font-['Space_Grotesk',sans-serif] flex items-center justify-center px-4">

    <div class="w-full max-w-sm">
        <div class="flex items-center gap-2 justify-center mb-8">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#F5A623] opacity-60"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#F5A623]"></span>
            </span>
            <h1 class="text-lg font-semibold tracking-tight">Server Pulse</h1>
        </div>

        <div class="bg-[#131822] border border-[#232936] rounded p-6">

            @if ($errors->any())
                <div class="mb-4 text-sm text-[#F87171] font-mono">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs text-[#8B93A1] mb-1.5">EMAIL</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="w-full bg-[#0B0E14] border border-[#232936] rounded px-3 py-2 text-sm font-mono focus:outline-none focus:border-[#F5A623]"
                    >
                </div>

                <div>
                    <label for="password" class="block text-xs text-[#8B93A1] mb-1.5">PASSWORD</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        class="w-full bg-[#0B0E14] border border-[#232936] rounded px-3 py-2 text-sm font-mono focus:outline-none focus:border-[#F5A623]"
                    >
                </div>

                <label class="flex items-center gap-2 text-xs text-[#8B93A1]">
                    <input type="checkbox" name="remember" class="rounded border-[#232936] bg-[#0B0E14]">
                    remember me
                </label>

                <button
                    type="submit"
                    class="w-full bg-[#F5A623] text-[#0B0E14] font-semibold text-sm rounded py-2 hover:opacity-90 transition-opacity"
                >
                    Sign in
                </button>
            </form>
        </div>
    </div>

</body>
</html>
