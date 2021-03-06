<?php

namespace Abs\SmsPkg;
use Abs\SmsPkg\SmsEvent;
use App\Address;
use App\Country;
use App\Http\Controllers\Controller;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Validator;
use Yajra\Datatables\Datatables;

class SmsEventController extends Controller {

	public function __construct() {
	}

	public function getSmsEventList(Request $request) {
		$sms_list = SmsEvent::withTrashed()
			->select(
				'smss.id',
				'smss.code',
				'smss.name',
				DB::raw('IF(smss.mobile_no IS NULL,"--",smss.mobile_no) as mobile_no'),
				DB::raw('IF(smss.email IS NULL,"--",smss.email) as email'),
				DB::raw('IF(smss.deleted_at IS NULL,"Active","Inactive") as status')
			)
			->where('smss.company_id', Auth::user()->company_id)
			->where(function ($query) use ($request) {
				if (!empty($request->sms_code)) {
					$query->where('smss.code', 'LIKE', '%' . $request->sms_code . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->sms_name)) {
					$query->where('smss.name', 'LIKE', '%' . $request->sms_name . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->mobile_no)) {
					$query->where('smss.mobile_no', 'LIKE', '%' . $request->mobile_no . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->email)) {
					$query->where('smss.email', 'LIKE', '%' . $request->email . '%');
				}
			})
			->orderby('smss.id', 'desc');

		return Datatables::of($sms_list)
			->addColumn('code', function ($sms_list) {
				$status = $sms_list->status == 'Active' ? 'green' : 'red';
				return '<span class="status-indicator ' . $status . '"></span>' . $sms_list->code;
			})
			->addColumn('action', function ($sms_list) {
				$edit_img = asset('public/theme/img/table/cndn/edit.svg');
				$delete_img = asset('public/theme/img/table/cndn/delete.svg');
				return '
					<a href="#!/sms-pkg/sms/edit/' . $sms_list->id . '">
						<img src="' . $edit_img . '" alt="View" class="img-responsive">
					</a>
					<a href="javascript:;" data-toggle="modal" data-target="#delete_sms"
					onclick="angular.element(this).scope().deleteSmsEvent(' . $sms_list->id . ')" dusk = "delete-btn" title="Delete">
					<img src="' . $delete_img . '" alt="delete" class="img-responsive">
					</a>
					';
			})
			->make(true);
	}

	public function getSmsEventFormData($id = NULL) {
		if (!$id) {
			$sms = new SmsEvent;
			$address = new Address;
			$action = 'Add';
		} else {
			$sms = SmsEvent::withTrashed()->find($id);
			$address = Address::where('address_of_id', 24)->where('entity_id', $id)->first();
			if (!$address) {
				$address = new Address;
			}
			$action = 'Edit';
		}
		$this->data['country_list'] = $country_list = Collect(Country::select('id', 'name')->get())->prepend(['id' => '', 'name' => 'Select Country']);
		$this->data['sms'] = $sms;
		$this->data['address'] = $address;
		$this->data['action'] = $action;

		return response()->json($this->data);
	}

	public function saveSmsEvent(Request $request) {
		// dd($request->all());
		try {
			$error_messages = [
				'code.required' => 'SmsEvent Code is Required',
				'code.max' => 'Maximum 255 Characters',
				'code.min' => 'Minimum 3 Characters',
				'name.required' => 'SmsEvent Name is Required',
				'name.max' => 'Maximum 255 Characters',
				'name.min' => 'Minimum 3 Characters',
				'gst_number.required' => 'GST Number is Required',
				'gst_number.max' => 'Maximum 191 Numbers',
				'mobile_no.max' => 'Maximum 25 Numbers',
				// 'email.required' => 'Email is Required',
				'address_line1.required' => 'Address Line 1 is Required',
				'address_line1.max' => 'Maximum 255 Characters',
				'address_line1.min' => 'Minimum 3 Characters',
				'address_line2.max' => 'Maximum 255 Characters',
				// 'pincode.required' => 'Pincode is Required',
				// 'pincode.max' => 'Maximum 6 Characters',
				// 'pincode.min' => 'Minimum 6 Characters',
			];
			$validator = Validator::make($request->all(), [
				'code' => 'required|max:255|min:3',
				'name' => 'required|max:255|min:3',
				'gst_number' => 'required|max:191',
				'mobile_no' => 'nullable|max:25',
				// 'email' => 'nullable',
				'address_line1' => 'required|max:255|min:3',
				'address_line2' => 'max:255',
				// 'pincode' => 'required|max:6|min:6',
			], $error_messages);
			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()->all()]);
			}

			DB::beginTransaction();
			if (!$request->id) {
				$sms = new SmsEvent;
				$sms->created_by_id = Auth::user()->id;
				$sms->created_at = Carbon::now();
				$sms->updated_at = NULL;
				$address = new Address;
			} else {
				$sms = SmsEvent::withTrashed()->find($request->id);
				$sms->updated_by_id = Auth::user()->id;
				$sms->updated_at = Carbon::now();
				$address = Address::where('address_of_id', 24)->where('entity_id', $request->id)->first();
			}
			$sms->fill($request->all());
			$sms->company_id = Auth::user()->company_id;
			if ($request->status == 'Inactive') {
				$sms->deleted_at = Carbon::now();
				$sms->deleted_by_id = Auth::user()->id;
			} else {
				$sms->deleted_by_id = NULL;
				$sms->deleted_at = NULL;
			}
			$sms->gst_number = $request->gst_number;
			$sms->save();

			if (!$address) {
				$address = new Address;
			}
			$address->fill($request->all());
			$address->company_id = Auth::user()->company_id;
			$address->address_of_id = 24;
			$address->entity_id = $sms->id;
			$address->address_type_id = 40;
			$address->name = 'Primary Address';
			$address->save();

			DB::commit();
			if (!($request->id)) {
				return response()->json(['success' => true, 'message' => ['SmsEvent Details Added Successfully']]);
			} else {
				return response()->json(['success' => true, 'message' => ['SmsEvent Details Updated Successfully']]);
			}
		} catch (Exceprion $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}
	public function deleteSmsEvent($id) {
		$delete_status = SmsEvent::withTrashed()->where('id', $id)->forceDelete();
		if ($delete_status) {
			$address_delete = Address::where('address_of_id', 24)->where('entity_id', $id)->forceDelete();
			return response()->json(['success' => true]);
		}
	}
}
