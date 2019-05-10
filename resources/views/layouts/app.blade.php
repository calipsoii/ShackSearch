<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/shacksearch.css') }}" rel="stylesheet">

    <!-- Scripts -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/babel-polyfill/6.26.0/polyfill.min.js"></script> -->
    <script src="{{ asset('js/polyfill.min.js') }}"></script>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-104761161-3"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'UA-104761161-3');
    </script>

</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light navbar-laravel" style="z-index:10;">
            <div class="container">
                <a class="navbar-brand" href="{{ route('home') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav mr-auto">
                            <li><a class="nav-link" href="{{ route('threads') }}">Threads</a></li>
                            <li><a class="nav-link" href="{{ route('search') }}">Search</a></li>
                        @auth
                            @can('viewAll',App\Chatty\post::class)
                                <li><a class="nav-link" href="{{ route('posts') }}">Posts</a></li>
                            @endcan
                            @can('viewAll',App\Chatty\word_cloud::class)
                                <li><a class="nav-link" href="{{ route('wordclouds') }}">Clouds</a></li>
                            @endcan
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Authentication Links -->
                        @guest
                            <li><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                            <!--<li><a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a></li>-->
                        @else
                            @can('viewAll',App\Chatty\event::class)
                                <li><a class="nav-link" href="{{ route('events') }}">Events</a></li>
                            @endcan
                            @can('viewAll',App\Chatty\dbAction::class)
                                <li><a class="nav-link" href="{{ route('logs') }}">Logs</a></li>
                            @endcan
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    {{ Auth::user()->name }} <span class="caret"></span>
                                </a>
                                <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                                    @can('viewAll',App\Chatty\app_setting::class)
                                        <a class="dropdown-item" href="{{ route('appsettings') }}">
                                            {{ __('Settings') }}
                                        </a>
                                    @endcan
                                    @can('viewAll',App\User::class)
                                        <a class="dropdown-item" href="{{ route('users') }}">
                                            {{ __('Users') }}
                                        </a>
                                    @endcan
                                    @can('viewAll',App\Role::class)
                                        <a class="dropdown-item" href="{{ route('roles') }}">
                                            {{ __('Roles') }}
                                        </a>
                                    @endcan
                                    @can('viewAll',App\Chatty\word_cloud_colorset::class)
                                        <a class="dropdown-item" href="{{ route('colorsets') }}">
                                            {{ __('Colorsets') }}
                                        </a>
                                    @endcan
                                    @can('viewAll',App\Chatty\monitor::class)
                                        <a class="dropdown-item" href="{{ route('monitors') }}">
                                            {{ __('Monitors') }}
                                        </a>
                                    @endcan
                                    <a class="dropdown-item" href="{{ route('users.profile', ['user' => Auth::user()->id]) }}">
                                        {{ __('Profile') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}"></script>
    <script defer src="https://use.fontawesome.com/releases/v5.0.13/js/all.js" integrity="sha384-xymdQtn1n3lH2wcu0qhcdaOpQwyoarkgLVxC/wZ5q7h9gHtxICrpcaSUfygqZGOe" crossorigin="anonymous"></script>
    <!-- <script src="https://unpkg.com/vue" crossorigin="anonymous"></script> -->
    <!-- <script src="https://unpkg.com/vuewordcloud" crossorigin="anonymous"></script> -->
    <script src="{{ asset('js/vue.js') }}"></script>
    <script src="{{ asset('js/VueWordCloud.js') }}"></script>
    

    
    @if(isset($GLOBALS["highlightedPost"]))
        <script>
            $('#'+{{$GLOBALS["highlightedPost"]}}).click();
        </script>
    @endif
</body>
</html>
