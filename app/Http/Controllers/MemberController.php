<?php

namespace App\Http\Controllers;

use Validator;
use App\Member;
use App\Point;
use App\Income;
use App\Refer;
use App\Setting;
use App\Type;
use App\Announcement;
use App\Sale;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Routing\Controller as BaseController;

class MemberController extends BaseController 
{
    use GetsJwtToken;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request) {
        $this->request = $request;
    }

    public function index() {
        $members = Member::with('refer')->get();
        $members->each(function($member) {
            if ($member->refer) {
                $member->refer->load('referer');
            }
        });
        return response()->json($members);
    }

    public function getProfile() {
        $token = $this->jwtToken();
        $id = $token->payload('context.id');

        $member = Member::with('referers', 'refer', 'incomes', 'points', 'withdrawals', 'sales', 'pointSales')->find($id);
        if ($member) {
            $member->referers->each(function($refer) {
                $refer->load('member');
            });

            $member->pointSales->each(function($pointSale) {
                $pointSale->load('item');
            });

            $announcement_ids = $member->announcementViews->pluck('announcement_id');
            $announcements = Announcement::whereNotIn('id', $announcement_ids)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $member->announcements = $announcements;
            unset($member->announcementViews);

            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function saveProfile(Request $request) {
        $token = $this->jwtToken();
        $id = $token->payload('context.id');
  
        $member = Member::find($id);
        if ($member) {
            if($request->input('password')) {
                $member->password = app('hash')->make($request->input('password'));
            }
            // $member->phone_number = $request->input('phone_number');
            // $member->card_number = $request->input('card_number');
            $member->save();

            return response($member);
        } else {
          return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }
    
    public function create(Request $request) {
        $this->validate($request, [
            'name' => 'required',
            'username' => 'required|unique:members',
            'password' => 'required'
        ]);

        $date = new \DateTime();
        $date->add(new \DateInterval('P7D'));

        $member = new Member;
        $member->name = $request->input('name');
        $member->username = $request->input('username');
        $member->password = app('hash')->make($request->input('password'));
        $member->phone_number = $request->input('phone_number');
        $member->card_number = $request->input('card_number');
        $member->entry_date = $request->input('entry_date') ? $request->input('entry_date') : date('Y-m-d');
        $member->next_period_date = $date->format('Y-m-d');
        $member->save();

        if ($request->input('refer_id')) {
            $refer_member = Member::find($request->input('refer_id'));
            if ($refer_member) {
                $refer = new Refer;
                $refer->member_id = $member->id;
                $refer->refer_id = $refer_member->id;
                $refer->refer_name = $refer_member->name;
                $refer->save();

                $task = new TaskController;
                $task->referIncomes($refer_member);

                $member->load('refer');
                $member->refer->load('referer');
            }
        }

        $setting_product_price = Setting::where('setting_field', 'product_price')->first();
        if ($setting_product_price) {
            $product_price = intval($setting_product_price->value);

            $sale = new Sale;
            $sale->product_price = $product_price;
            $member->sales()->save($sale);
        }

        return response($member, 201);
    }

    public function get($id) {
        $member = Member::with('refer')->find($id);
        if($member) {
            if ($member->refer) {
                $member->refer->load('referer');
            }
            
            return response($member);
        }
        else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function update(Request $request, $id) {
        $member = Member::find($id);
        if($member) {
            if($request->input('password')) {
                $member->password = app('hash')->make($request->input('password'));
            }
            $member->phone_number = $request->input('phone_number');
            $member->card_number = $request->input('card_number');
            $member->save();

            if ($member->refer) {
                $member->refer->load('referer');
            }

            return response($member);
        }
        else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function delete($id) {
        $member = Member::find($id);
        if($member) {
            $member->delete();
            return response('Deleted Successfully');
        }
        else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function getIncomes(Request $request, $id) {
        $member = Member::with('incomes')->find($id);
        if ($member) {
            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function getWithdrawals(Request $request, $id) {
        $member = Member::with('withdrawals')->find($id);
        if ($member) {
            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function getPoints(Request $request, $id) {
        $member = Member::with('points')->find($id);
        if ($member) {
            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function getSales(Request $request, $id) {
        $member = Member::with('sales')->find($id);
        if ($member) {
            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function getRefers(Request $request, $id) {
        $member = Member::with('referers')->find($id);
        if ($member) {
            $member->referers->each(function($refer) {
                $refer->load('member');
            });
            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }

    public function getPointSales(Request $request, $id) {
        $member = Member::with('pointSales')->find($id);
        if ($member) {
            $member->pointSales->each(function($pointSale) {
                $pointSale->load('item');
            });
            return response()->json($member);
        } else {
            return response(['error' => __('Not found data for #:ID', ['ID' => $id])], 404);
        }
    }
 
    public function createManual(Request $request) {
        $data = $request->all();

        $task = new TaskController;
        $member = $task->addMember($data);
        if ($member) {
            return response($member, 201);
        } else {
            return response(['error' => 'Could not create'], 404);
        }
    }
}