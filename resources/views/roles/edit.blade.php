@extends('layouts.app')

@section('content')

<div class="container">

    @cannot('update',$role)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to edit this Role.
            </div>
        </div>
    @else

        <div class="row justify-content-center">
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header">{{ __('Edit Role') }}</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('roles.update', $role->id) }}">
                        @csrf
                        @method('PUT')

                            <!-- Form for editing existing roles -->
                            <div class="form-group row">
                                <label for="new-role-name"  class="col-sm-3 col-form-label text-md-right">{{ __('Name') }}</label>
                                <div class="col-md-8">
                                    <input id="new-role-name" type="text" class="form-control{{ $errors->has('roleName') ? 'is-invalid' : '' }}" name="roleName" value="{{ old('roleName', $role->name) }}" required>
                                    @if ($errors->has('roleName'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('roleName') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="new-role-description"  class="col-sm-3 col-form-label text-md-right">{{ __('Description') }}</label>
                                <div class="col-md-8">
                                    <input id="new-role-description" type="text" class="form-control{{ $errors->has('roleDescr') ? 'is-invalid' : '' }}" name="roleDescr" value="{{ old('roleDescr', $role->description) }}" required>
                                    @if ($errors->has('roleDescr'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('roleDescr') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row mb-0">
                                <div class="col-md-8 offset-md-3">
                                    <button type="submit" class="btn btn-primary" @cannot('update',$role) disabled @endcannot>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>

                        </form>
                        <div class="mt-3">
                            <a href="{{ route('roles') }}">< Return to Roles</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endcannot
</div>

@endsection