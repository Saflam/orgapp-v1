<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <title>Committee Module - {{ config('app.name', 'Laravel') }}</title>

        <meta name="description" content="{{ $description ?? '' }}">
        <meta name="keywords" content="{{ $keywords ?? '' }}">
        <meta name="author" content="{{ $author ?? '' }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        {{-- Vite CSS --}}
        {{-- {{ module_vite('build-committee', 'resources/assets/sass/app.scss') }} --}}
    </head>

    <body>
        {{ $slot }}

        {{-- Vite JS --}}
        {{-- {{ module_vite('build-committee', 'resources/assets/js/app.js') }} --}}
    
<style>
.committee-button{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.65rem 1rem;border:1px solid var(--border-color,#d1d5db);border-radius:.55rem;background:var(--surface-color,#fff);color:var(--text-color,#111827);font-weight:600;cursor:pointer;text-decoration:none}
.committee-button-primary{background:var(--primary-color,#1d4ed8);border-color:var(--primary-color,#1d4ed8);color:#fff}
.committee-form-card{margin:1rem 0;padding:1.25rem;border:1px solid var(--border-color,#e5e7eb);border-radius:.85rem;background:var(--card-background,#fff);box-shadow:0 1px 3px rgba(0,0,0,.04)}
.committee-form-card h2{margin:0 0 1rem;font-size:1.1rem;font-weight:700}
.committee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.committee-form-field{display:flex;flex-direction:column;gap:.4rem;min-width:0}
.committee-form-field label{font-size:.875rem;font-weight:600}
.committee-form-field-full{grid-column:1/-1}
.committee-form-actions{grid-column:1/-1;display:flex;gap:.65rem;align-items:center;margin-top:.25rem}
.committee-field-error{color:#b91c1c;font-size:.8rem}
.committee-alert{padding:.75rem 1rem;margin:.75rem 0;border-radius:.6rem}
.committee-alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
@media(max-width:640px){.committee-form-grid{grid-template-columns:1fr}.committee-form-field-full,.committee-form-actions{grid-column:auto}}
</style>

</body>
</html>
