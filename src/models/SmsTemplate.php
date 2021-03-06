<?php

namespace Abs\SmsPkg;

use Abs\HelperPkg\Traits\SeederTrait;
use App\Company;
use App\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsTemplate extends Model {
	use SeederTrait;
	use SoftDeletes;
	protected $table = 'sms_templates';
	protected $fillable = [
		'company_id',
		'name',
		'vendor_template_id',
		'description',
		'content',
	];

	public function params() {
		return $this->hasMany('Abs\SmsPkg\SmsTemplateParameter', 'sms_template_id');
	}

	public static function createFromObject($record_data) {

		$errors = [];
		$company = Company::where('code', $record_data->company)->first();
		if (!$company) {
			dump('Invalid Company : ' . $record_data->company);
			return;
		}

		$admin = $company->admin();
		if (!$admin) {
			dump('Default Admin user not found');
			return;
		}

		$type = Config::where('name', $record_data->type)->where('config_type_id', 89)->first();
		if (!$type) {
			$errors[] = 'Invalid Tax Type : ' . $record_data->type;
		}

		if (count($errors) > 0) {
			dump($errors);
			return;
		}

		$record = self::firstOrNew([
			'company_id' => $company->id,
			'name' => $record_data->tax_name,
		]);
		$record->type_id = $type->id;
		$record->created_by_id = $admin->id;
		$record->save();
		return $record;
	}

	public static function createMultipleFromArray($records) {
		foreach ($records as $data) {
			$record = self::firstOrNew([
				'company_id' => $data['company_id'],
				'name' => $data['name'],
			]);
			$record->fill($data);
			$record->save();
		}
	}

}
