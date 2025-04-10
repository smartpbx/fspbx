<?php

namespace App\Http\Controllers;


use App\Models\User;
use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\Contact;
use App\Models\UserGroup;
use App\Models\UserSetting;
use Illuminate\Support\Str;
use App\Models\DomainGroups;
use Illuminate\Http\Request;
use App\Models\UserAdvFields;
use Illuminate\Validation\Rule;
use App\Models\GroupPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserDomainPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Http\Kernel;
use function PHPUnit\Framework\isNull;

use function PHPUnit\Framework\isEmpty;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Models\UserDomainGroupPermissions;

class UsersController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

   /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        if($request->hasHeader('X-Inertia')) {
            return Inertia::location(route($request->route()->getName()));
        }

        // Check permissions
        if (!userCheckPermission("user_view")){
            return redirect('/');
        }

        $data=array();
        $domain_uuid=Session::get('domain_uuid');
        $data['users']=User::where('domain_uuid',$domain_uuid)->orderBy('username','asc')->get();

        //assign permissions
        $permissions['add_new'] = userCheckPermission('user_add');
        $permissions['edit'] = userCheckPermission('user_edit');
        $permissions['delete'] = userCheckPermission('user_delete');
        $permissions['add_user'] = userCheckPermission('user_add');

        return view('layouts.users.list')
            ->with($data)
            ->with('permissions',$permissions);
    }


    /**
     * Show the create user form.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function create(Request $request)
    {

	    if (!userCheckPermission('user_add') || !userCheckPermission('user_edit')) {
            return redirect('/');
	    }

        $user = new User();
        $user->user_enabled = "true";

        // Get all permission groups that are lower or equal to the logged in users level
        $all_groups = Groups::where('group_level','<=', Session::get('user')['group_level'])
        ->where (function($query) {
            $query->where('domain_uuid',null)
            ->orWhere('domain_uuid', Session::get('domain_uuid'));
        })
        ->get();

        //get all active domains
        $all_domains = Domain::where('domain_enabled','true')
        ->get();

        //get all domain groups
        $all_domain_groups = DomainGroups::get();

        // Get groups that have domain_select permission
        $domain_select_groups = array();
        foreach($all_groups as $group){
            $group_permission = GroupPermissions:: where ('group_uuid', $group->group_uuid)
                -> where ('permission_name', 'domain_select')
                -> where ('permission_assigned', 'true')
                -> get();
            if (!$group_permission->isEmpty()) {
                $domain_select_groups[] = $group->group_uuid;
            }
        }

        //Set defaults
        $user_language= new UserSetting();
        $user_language->user_setting_value = "en-us";
        $user_time_zone= new UserSetting();
        $user_time_zone->user_setting_value = "America/Los_Angeles";

        $data=array();
        $data['user'] = $user;
        $data['all_groups']=$all_groups;
        $data['all_domains']=$all_domains;
        $data['all_domain_groups']=$all_domain_groups;
        $data['domain_select_groups']=json_encode($domain_select_groups);
        $data['user_language']=$user_language;
        $data['user_time_zone']=$user_time_zone;
        $data['languages']=DB::table('v_languages')->get();
        
        return view('layouts.users.createOrUpdate')->with($data);

    }


    public function getDomainID($domain_name){
        return Domain::where('domain_name',$domain_name)->pluck('domain_uuid')->first();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  guid  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(User $user)
    {
        if(request()->hasHeader('X-Inertia')) {
            return Inertia::location(route(request()->route()->getName()));
        }

        //check permissions
	    if (!userCheckPermission('user_edit')) {
            return redirect('/');
	    }

        $user_language=[];
        $user_time_zone=[];
        if(!empty($user->setting)){
            foreach($user->setting as $setting){
                if($setting->user_setting_subcategory=='language'){
                    $user_language=$setting;
                }
                if($setting->user_setting_subcategory=='time_zone'){
                    $user_time_zone=$setting;
                }
            }
        }
        
        // Get all permission groups that are lower or equal to the logged in users level
        $all_groups = Groups::where('group_level','<=', Session::get('user')['group_level'])
            ->where (function($query) {
                $query->where('domain_uuid',null)
                ->orWhere('domain_uuid', Session::get('domain_uuid'));
            })
            ->get();

        // Get groups that have domain_select permission
        $domain_select_groups = array();
        foreach($all_groups as $group){
            $group_permission = GroupPermissions:: where ('group_uuid', $group->group_uuid)
                -> where ('permission_name', 'domain_select')
                -> where ('permission_assigned', 'true')
                -> get();
            if (!$group_permission->isEmpty()) {
                $domain_select_groups[] = $group->group_uuid;
            }
        }

        //get all active domains
        $all_domains = Domain::where('domain_enabled','true')
            ->get();

        //get all domain groups
        $all_domain_groups = DomainGroups::get();

        $data=array();
        $data['user_groups']=$user->groups();
        $data['all_groups']=$all_groups;
        $data['all_domains']=$all_domains;
        $data['all_domain_groups']=$all_domain_groups;
        $data['domain_select_groups']=json_encode($domain_select_groups);
        
        $data['assigned_domains'] = collect();
        foreach ($user->domain_permissions as $domain_permission) {
            $data['assigned_domains'] ->push($domain_permission->domain);
        }

        $data['assigned_domain_groups'] = collect();
        foreach ($user->domain_group_permissions as $domain_group_permission) {
            $data['assigned_domain_groups'] ->push($domain_group_permission->domain_group);
        }

        $data['languages']=DB::table('v_languages')->get();
        $data['user']=$user;
        $data['user_language']=$user_language;
        $data['user_time_zone']=$user_time_zone;
        
        $records = $user->setting()
            -> where('user_setting_name','!=','system_default')
            -> where('user_setting_subcategory','!=','language')
            -> where('user_setting_subcategory','!=','time_zone')
            -> orderBy('user_setting_category')->get();
                
        $data['settings']=$records;
                
        return view('layouts.users.createOrUpdate')->with($data);


    }

    public function store(Request $request, User $user)
    {
        $attributes = [
            'user_email' => 'email',
            'groups' => 'roles'
        ];

        $validator = Validator::make($request->all(), [
            'first_name' =>'required|string|max:40',
            'last_name' => 'nullable|string|max:40',
            'user_email' => [
                'required',
                Rule::unique('App\Models\User','user_email')->ignore($user->user_uuid,'user_uuid'),
                'email:rfc'
            ],
            'groups' => 'required|array',
            'time_zone' => 'nullable',
            'language' => 'nullable',
            'user_enabled' => 'present', 
            'domains' => 'nullable', 
            'domain_groups' => 'nullable',
            'domain_uuid' => 'present',         
  
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        if (isset($attributes['user_enabled']) && $attributes['user_enabled']== "on")  $attributes['user_enabled'] = "true";    
 
        //Make username by combining first name and last name
        $attributes['username'] = $attributes['first_name'];
        if(!empty($attributes['last_name'])){
            $attributes['username'] .= '_' . $attributes['last_name'];
        }

        $attributes['user_email'] = strtolower(trim($attributes['user_email']));

        // Generate a secure password 
        $attributes['password'] = Hash::make(Str::random(25));

        $attributes['add_user'] = Auth::user()->username;
        $user->fill($attributes);   
        $user->save();

        $user_name_info=new UserAdvFields();
        $user_name_info->first_name=$attributes['first_name'];
        $user_name_info->last_name=$attributes['last_name'];
        $user->user_adv_fields()->save($user_name_info);

        // Save user groups 
        if (isset($attributes['groups'])) {
            foreach($attributes['groups'] as $group){
                $group_name = Groups::where('group_uuid',$group)->pluck('group_name')->first();
                    $user_group=new UserGroup();
                    $user_group->domain_uuid = Session::get('domain_uuid');
                    $user_group->group_name = $group_name;
                    $user_group->group_uuid = $group;
                    $user_group->insert_date = date('Y-m-d H:i:s');
                    $user_group->insert_user = Session::get('user_uuid');
                    $user->user_groups()->save($user_group);
            }
        } 

        if (isSuperAdmin()){
            if (isset($attributes['domains'])) {
                foreach($attributes['domains'] as $res_domain){
                    $dom_per=new UserDomainPermission();
                    $dom_per->domain_uuid=$res_domain;
                    $user->domain_permissions()->save($dom_per);
                }
            }

            if (isset($attributes['domain_groups'])) {
                foreach($attributes['domain_groups'] as $domain_group){
                    $dom_per = new UserDomainGroupPermissions();
                    $dom_per->domain_group_uuid = $domain_group;
                    $user->domain_group_permissions()->save($dom_per);
                }
            }

        }

        $language=new UserSetting();
        $language->domain_uuid=Session::get('domain_uuid');;
        $language->user_setting_category='domain';
        $language->user_setting_subcategory='language';
        $language->user_setting_name='code';
        $language->user_setting_value=$attributes['language'];
        $language->user_setting_enabled='t';
        
        $time_zone=new UserSetting();
        $time_zone->domain_uuid=Session::get('domain_uuid');;
        $time_zone->user_setting_category='domain';
        $time_zone->user_setting_subcategory='time_zone';
        $time_zone->user_setting_name='name';
        $time_zone->user_setting_value=$attributes['time_zone'];
        $time_zone->user_setting_enabled='t';

        $user->setting()->saveMany([$language,$time_zone]);

        return response()->json([
            'status' => 'success',
            'user_uuid' => $user->user_uuid,
            'message' => 'User has been saved'
        ]);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, User $user)
    {
        $attributes = [
            'user_email' => 'email',
            'groups' => 'roles'
        ];

        $validator = Validator::make($request->all(), [
            'first_name' =>'required|string|max:40',
            'last_name' => 'nullable|string|max:40',
            'user_email' => [
                'required',
                Rule::unique('App\Models\User','user_email')->ignore($user->user_uuid,'user_uuid'),
                'email:rfc'
            ],
            'groups' => 'required|array',
            'is_on_premise' => 'nullable|boolean',
            'on_premise_pbx_ip' => 'nullable|string|ipv4',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user basic info
        $user->user_email = $request->user_email;
        $user->user_enabled = $request->user_enabled;
        $user->save();

        // Update user advanced fields
        $userAdvFields = $user->user_adv_fields ?? new UserAdvFields();
        $userAdvFields->user_uuid = $user->user_uuid;
        $userAdvFields->first_name = $request->first_name;
        $userAdvFields->last_name = $request->last_name;
        
        // Only update on-premise fields if user is superadmin
        if (isSuperAdmin()) {
            $userAdvFields->is_on_premise = $request->is_on_premise ?? false;
            $userAdvFields->on_premise_pbx_ip = $request->on_premise_pbx_ip;
        }
        
        $userAdvFields->save();

        // Update user groups
        UserGroup::where('user_uuid', $user->user_uuid)->delete();
        foreach ($request->groups as $group_uuid) {
            $userGroup = new UserGroup();
            $userGroup->user_uuid = $user->user_uuid;
            $userGroup->group_uuid = $group_uuid;
            $userGroup->save();
        }

        return response()->json(['message' => 'User updated successfully'], 200);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if(isset($user)){
            $deleted = $user->delete();
            foreach ($user->setting as $setting) {
                $setting->delete();
            }

            foreach ($user->user_groups as $group) {
                $group->delete();
            }

            if ($deleted){
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected users have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting this user'
                    ]
                ]);
            }
        }
    }
    
    public function show ($id){
        //
    }

}
