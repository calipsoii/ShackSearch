@extends('layouts.app')

@section('content')

<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',App\Role::class)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Roles.
            </div>
        </div>
    @else
        <!-- Table/form for creating, displaying and deleting existing roles -->
        <div class="row">
            <div class="col-md-10 offset-md-1">

                <div class="divTable">
                    <div class="divTableHeading">
                        <div class="divTableRow">
                            <div class="divTableHead divNoRightBorder">Name</div>
                            <div class="divTableHead divNoRightBorder">Description</div>
                            <div class="divTableHead">&nbsp;</div>
                        </div>
                    </div>
                    <div class="divTableBody">
                        @if(count($roles) > 0)
                            @foreach($roles as $role)
                            <div class="divTableRow">
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    <a href="{{ route('roles.edit',$role->id) }}">{{ $role->name }}</a>
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $role->description }}
                                </div>
                                <div class="divTableCell divNoTopBorder">
                                    <form action="{{ route('roles.destroy', $role->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" class="btn btn-danger divTableButton" name="btnDelete" @cannot('delete',$role) disabled @endcannot>{{ __('Delete')}}</button>
                                        </form>
                                </div>
                            </div>
                            @endforeach
                        @endif
                        <form class="divTableRow" action="{{ route('roles') }}" method="POST">
                        @csrf
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    <input type="text" name="roleName" value="{{ old('roleName') }}" style="width:100%;" required>
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    <input type="text" name="roleDescr" value="{{ old('roleDescr') }}" style="width:100%;" required>
                                </div>
                                <div class="divTableCell divNoTopBorder">
                                    <button type="submit" class="btn btn-success divTableButton" name="btnAdd" @cannot('create',App\Role::class) disabled @endcannot>{{ __('Create') }}</button>
                                </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    @endcannot
</div>

@endsection