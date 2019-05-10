@extends('layouts.app')

@section('content')

<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('view',$user)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view this user account.
            </div>
        </div>
    @else
        <div class="row justify-content-center">
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header">{{ __('Edit User') }}</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('users.update', $user->id) }}">
                        @csrf
                        @method('PUT')

                            <!-- Form for editing existing users -->
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
                            <div class="form-group row">
                                <label for="edit-password"  class="col-sm-3 col-form-label text-md-right">{{ __('Password') }}</label>
                                <div class="col-md-8">
                                    <input id="edit-password" type="password" class="form-control{{ $errors->has('password') ? 'is-invalid' : '' }}" name="password" value="{{ old('password') }}">
                                    @if ($errors->has('password'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('password') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="edit-user-email" class="col-sm-3 col-form-label text-md-right">{{ __('Email') }}</label>
                                <div class="col-md-8">
                                    <?php
                                        $displayEmail = '';
                                        if(Auth::user()->can('viewEmail',App\User::class)) {
                                            $displayEmail = $user->email;
                                        }
                                    ?>
                                    <input id="edit-user-email" type="text" class="form-control{{ $errors->has('email') ? 'is-invalid' : '' }}" name="email" value="{{ old('email', $displayEmail) }}" required>
                                    @if ($errors->has('email'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="edit-user-roles" class="col-sm-3 col-form-label text-md-right">{{ __('Roles') }}</label>
                                <div class="col-md-8">
                                    <select class="custom-select" name="roleSelect[]" multiple>
                                        @if($roles->count() > 0)
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" @if(in_array($role->id,$userRoles)) selected @endif>{{ $role->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row mb-0">
                                <div class="col-md-8 offset-md-3">
                                    <button type="submit" class="btn btn-primary" @cannot('delete',$user) disabled @endcannot>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>

                        </form>
                        <div class="mt-3">
                            <a href="{{ route('users') }}">< Return to Users</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endcannot
</div>

@endsection