<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="">
<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'Laravel') }}</title>
<!-- Fonts -->
<link rel="stylesheet" href="{{ mix('css/heebo.css') }}">
<link rel="stylesheet" href="{{ mix('css/poppins.css') }}">
<link rel="preload" href="{{ asset('fonts/poppins-v20-latin-500.woff2') }}" as="font" type="font/woff"
  crossorigin>
<link rel="preload" href="{{ asset('fonts/poppins-v20-latin-regular.woff2') }}" as="font" type="font/woff"
  crossorigin>
<link rel="preload" href="{{ asset('fonts/heebo-v21-latin-regular.woff2') }}" as="font" type="font/woff"
  crossorigin>
<link rel="preload" href="{{ asset('fonts/heebo-v21-latin-500.woff2') }}" as="font" type="font/woff" crossorigin>
<link href="{{ mix('css/app.css') }}" rel="stylesheet">
<link rel="icon" href="{{ mix('images/favicon.png') }}">
<style>
  [x-cloak] {
    display: none !important;
  }
</style>
