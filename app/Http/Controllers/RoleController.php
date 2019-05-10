<?php

namespace App\Http\Controllers;

use DB;
use App\Role;
use Auth;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $roles = Role::orderBy('name')->get();

        return view('roles.index', [
            'roles' => $roles
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create',Role::class);

        //
        $validator = \Validator::make($request->all(), [
            'roleName' => 'required|max:255',
            'roleDescr' => 'required|max:255',
        ]);

        if($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator);
        }

        $role = new Role;
        $role->name = $request->roleName;
        $role->description = $request->roleDescr;
        $role->save();

        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function show(Role $role)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function edit(Role $role)
    {
        //
        return view('roles.edit')->with('role',$role);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update',$role);
        //
        $validator = \Validator::make($request->all(), [
            'roleName' => 'required|max:255',
            'roleDescr' => 'required|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->route('roles' . $role->id . '/edit')
                ->withErrors($validator)
                ->withInput();
        } else {
            $role->name = $request->input('roleName');
            $role->description = $request->input('roleDescr');
            $role->save();

            return redirect()
                ->route('roles');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Role  $role
     * @return \Illuminate\Http\Response
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete',$role);
        //
        if(count(DB::table('role_user')->where('role_id',$role->id)->get()))
        {
            return back()
                ->with('error','Role assigned to users. Remove role from all users before deleting.');
        }
        else
        {
            $role->delete();
            return redirect()
                    ->route('roles');
        }

    }
}
