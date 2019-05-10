@if (session('error'))
    <div class="alert alert-danger">
        <strong>Error!</strong>

        <br><br>

        <ul>
            @foreach (session('error') as $message)
                    <li>{!! $message !!}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning">
        <strong>Warning!</strong>

        <br><br>

        <ul>
            @foreach (session('warning') as $message)
                    <li>{{ $message }}</li>
            @endforeach
        </ul>

    </div>
@endif

@if (session('success'))
    <div class="alert alert-success">
        <strong>Success!</strong>

        <br><br>

        <ul>
            @foreach (session('success') as $message)
                    <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('information'))
    <div class="alert">
        {{ session('information') }}
    </div>
@endif

@if (session('messageIds'))
    <div class="alert alert-success">
        <strong>Success!</strong>

        <br><br>

        <ul>
            @foreach (session('messageIds') as $message)
                    <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif