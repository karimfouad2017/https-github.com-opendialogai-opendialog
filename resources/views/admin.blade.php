<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <script>window.Laravel = {!! json_encode(['apiToken' => auth()->user()->api_token ?? null, 'userId' => auth()->user()->id, 'twoFactorAuthEnabled' => auth()->user()->twoFactorAuthEnabled()]) !!};</script>

    <!-- Scripts -->
    <script src="{{ mix('js/app.js') }}" defer></script>

    <!-- Styles -->
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">

    <script>window.DashboardCards = {!! json_encode(config('admin-stats.cards')) !!};</script>

    <script>window.cognito = {!! json_encode(config('services.cognito')) !!};</script>

    <script>window.NavigationItems = {!! json_encode(config('admin-navigation')) !!};</script>

    <script>window.user = {!! json_encode(auth()->user()) !!};</script>

    <script>window.ODVersion = '{{ config('dashboard.version') }}';</script>

    <script>window.notificationsEndpoint = '{{ config('dashboard.notifications_endpoint') }}';</script>

    @include("includes.gtm-head")
  </head>

  <body class="app">
    @include("includes.gtm-body")

    <div id="app">
      <app></app>
    </div>


    <script>
      window.openDialogSettings = {
        url: "{{ env("APP_URL") }}",
        user: {
          custom: {
            selected_scenario: "{{request()->get('scenario')}}"
          }
        },
      };
    </script>

  </body>
</html>
