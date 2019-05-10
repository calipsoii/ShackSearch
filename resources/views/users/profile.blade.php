@extends('layouts.app')

@section('content')

<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewProfile',$user)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view this user profile.
            </div>
        </div>
    @else
        <div class="row justify-content-center">
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header">{{ __('Edit Profile') }}</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('users.profile', $user->id) }}">
                        @csrf
                        @method('PUT')
                            <div class="form-group row">
                                <label for="edit-user-name"  class="col-sm-3 col-form-label text-md-right">{{ __('Display Name') }}</label>
                                <div class="col-md-8">
                                    <input id="edit-user-name" type="text" class="form-control{{ $errors->has('name') ? 'is-invalid' : '' }}" name="name" value="{{ old('name', $user->name) }}" required>
                                    @if ($errors->has('name'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('name') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="edit-username"  class="col-sm-3 col-form-label text-md-right">{{ __('Username') }}</label>
                                <div class="col-md-8">
                                    <input id="edit-username" type="text" class="form-control{{ $errors->has('username') ? 'is-invalid' : '' }}" name="username" value="{{ old('username', $user->username) }}" required>
                                    @if ($errors->has('username'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('username') }}</strong>
                                        </span>
                                    @endif
                                    </div>
                            </div>
                            <div class="form-group row mb-0">
                                <div class="col-md-8 offset-md-3">
                                    <button type="submit" class="btn btn-primary" @cannot('update',$user) disabled @endcannot>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="mt-3">
                            <a href="{{ route('home') }}">< Return Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endcannot

</div>

@endsection