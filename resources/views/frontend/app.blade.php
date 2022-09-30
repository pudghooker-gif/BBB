<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<title>@yield('page-title') {{ settings('app_name') }}</title>
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="description" content="@yield('page-title') - {{ settings('app_name') }}">
	<meta name="viewport" content="width=device-width">
	<link rel="icon" href="/frontend/Default/img/favicon.png">
	<meta property="og:image" content="/frontend/Default/img/vladA.png">

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat+Alternates:wght@200&display=swap" rel="stylesheet">
</head>
<body class="@yield('add-body-class')">
	<div id="app"></div>
	<script src="{{ mix('js/app.js') }}" async defer></script>
</body>
</html>