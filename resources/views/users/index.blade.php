@extends('layouts.app')

@section('content')

<div class="container">

    @cannot('viewAll',Auth::user())
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to view Users.
            </div>
        </div>
    @else
        <!-- Table/form for creating, displaying and deleting existing users -->
        <div class="row">
            <div class="col-md-10 offset-md-1">

                <div class="divTable">
                    <div class="divTableHeading">
                        <div class="divTableRow">
                            <div class="divTableHead divNoRightBorder">Username</div>
                            <div class="divTableHead divNoRightBorder">Name</div>
                            <div class="divTableHead divNoRightBorder">Roles</div>
                            <div class="divTableHead divNoRightBorder">Clouds</div>
                            <div class="divTableHead divNoRightBorder">Last Login</div>
                            <div class="divTableHead">&nbsp;</div>
                        </div>
                    </div>
                    <div class="divTableBody">
                        @if(count($users) > 0)
                            @foreach($users as $user)
                            <div class="divTableRow">
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    <a href="{{ route('users.edit',$user->id) }}">{{ $user->username }}</a>
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $user->name }}
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    @foreach($user->roles as $userRole)
                                        {{ $userRole->name }}
                                    @endforeach
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $user->clouds->count() }}
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $user->last_login }}
                                </div>
                                <div class="divTableCell divNoTopBorder">
                                    <form action="{{ route('users.destroy', $user->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" class="btn btn-danger divTableButton" name="btnDelete" @cannot('delete',$user) disabled @endcannot>{{ __('Delete')}}</button>
                                        </form>
                                </div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endcannot
</div>

@endsection