@extends('layouts.app')

@section('content')

<div class="container">

    @include('returnMessage')
    @include('errors')

    @cannot('viewAll',$colorset)
        <div class="row">
            <div class="col-md-10 offset-md-1 alert alert-secondary">
                You are not authorized to edit this Colorset.
            </div>
        </div>
    @else

        <div class="row justify-content-center">
            <div class="col-md-10 mb-3">
                <div class="card">
                    <div class="card-header">Edit Colorset</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('colorsets.update', $colorset->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="form-group row">
                                <label for="colorset-name" class="col-sm-4 col-form-label text-sm-right">{{ __('Name') }}</label>
                                <div class="col-sm-4">
                                    <input id="colorset-name" type="text" class="form-control{{ $errors->has('colorsetName') ? 'is-invalid' : '' }}" name="colorsetName" value="{{ old('colorsetName', $colorset->name) }}" required>
                                    @if ($errors->has('colorsetName'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('colorsetName') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="colorset-description"  class="col-sm-4 col-form-label text-sm-right">{{ __('Description') }}</label>
                                <div class="col-sm-4">
                                    <input id="colorset-description" type="text" class="form-control{{ $errors->has('colorsetDescr') ? 'is-invalid' : '' }}" name="colorsetDescr" value="{{ old('colorsetDescr', $colorset->descr) }}" required>
                                    @if ($errors->has('colorsetDescr'))
                                        <span class="invalid-feedback">
                                            <strong>{{ $errors->first('colorsetDescr') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="colorset-is-default" class="col-7 col-sm-4 col-form-label text-sm-right">{{ __('Default Colorset?') }}</label>
                                <div class="col-4">
                                    <input class="ml-1 mr-3" type="checkbox" id="colorset-is-default" name="defaultColorset" value="defaultColorset" {{ $colorset->is_default ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="colorset-active" class="col-7 col-sm-4 col-form-label text-sm-right">{{ __('Active?') }}</label>
                                <div class="col-4">
                                    <input class="ml-1 mr-3" type="checkbox" id="colorset-active" name="active" value="active" {{ $colorset->active ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-sm-4 offset-sm-4">
                                    <button type="submit" class="btn btn-primary divTableButton" @cannot('update',$colorset) disabled @endcannot>
                                        {{ __('Save') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="form-group row">
                            <div class="col-sm-4 offset-sm-4">
                                <form action="{{ route('colorsets.destroy', ['colorset' => $colorset->id]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger divTableButton" name="btnDelete" onclick="return confirm('Are you sure you want to delete colorset {{ $colorset->name }}?');" @cannot('delete',$colorset) disabled @endcannot>{{ __('Delete')}}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table for creating/editing/deleting colors within a colorset -->
        <div class="row">
            <div class="col-md-10 offset-md-1">

                <div class="divTable">
                    <div class="divTableHeading">
                        <div class="divTableRow">
                            <div class="divTableHead divNoRightBorder">Sequence</div>
                            <div class="divTableHead divNoRightBorder">&nbsp;</div>
                            <div class="divTableHead divNoRightBorder">Color</div>
                            <div class="divTableHead">&nbsp;</div>
                        </div>
                    </div>
                    <div class="divTableBody">
                        @if(count($colors) > 0)
                            @foreach($colors as $color)
                            <div class="divTableRow">
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $color->sequence_num }}
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder" style="background-color:{{ $color->color }};">
                                    &nbsp;
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    {{ $color->color }}
                                </div>
                                <div class="divTableCell divNoTopBorder">
                                    <form action="{{ route('colorsets.destroycolor', $color->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" class="btn btn-danger divTableButton" name="btnDelete" @cannot('delete',$colorset) disabled @endcannot>{{ __('Delete')}}</button>
                                        </form>
                                </div>
                            </div>
                            @endforeach
                        @endif
                        <form class="divTableRow" action="{{ route('colorsets.createcolor', $colorset->id) }}" method="POST">
                        @csrf
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    <input type="text" name="colorSeqNum" value="{{ old('colorSeqNum') }}" style="width:100%;" required>
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    &nbsp;
                                </div>
                                <div class="divTableCell divNoRightBorder divNoTopBorder">
                                    <input type="text" name="colorName" value="{{ old('colorName') }}" style="width:100%;" required>
                                </div>
                                <div class="divTableCell divNoTopBorder">
                                    <button type="submit" class="btn btn-success divTableButton" name="btnAdd" @cannot('create',App\Chatty\word_cloud_colorset::class) disabled @endcannot>{{ __('Create') }}</button>
                                </div>
                        </form>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="{{ route('colorsets') }}">< Return to Colorsets</a>
                </div>

            </div>
        </div>

        
    @endcannot
</div>

@endsection