<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CertificateStaff;
use App\Models\ArtisanCommandLog;
use App\Models\PointLog as PointLogModel;
use App\Models\AuthorityGroupHasStaff as GroupStaff;

class ConvertOneTimePoint extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:one-time-point-convert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '一次性积分转化';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $staff_sns = GroupStaff::pluck('staff_sn')->unique()->values();

        $excloud = PointLogModel::where('source_id', 3)->whereNull('changed_at')->pluck('staff_sn');

        $filters = $staff_sns->filter(function ($val) use ($excloud) {
            return !in_array($val, $excloud->toArray());
        })->values();

        if (empty($filters)) {
            return false;
        }

        $config = CommonConfig::byNamespace('basepoint')->byName('education')->first();
        $toConfig = json_decode($config['value'], true);

        $users = app('api')->client()->getStaff(['filters' => "staff_sn={$filters};status_id>=0"]);

        $commandModel = $this->createLog();
        try {
            \DB::beginTransaction();

            foreach ($users as $key => $user) {
                $match = array_first($toConfig, function ($item) use ($user) {
                    return $item['name'] == $user['education'];
                });

                if (!empty($match)) {
                    $this->createPointLog($user, $match['point']);
                }
            }

            $commandModel->save();

            \DB::commit();
        } catch (Exception $e) {
            $commandModel->status = 2;
            $commandModel->save();

            \DB::rollBack();
        }
    }

    /**
     * 记录考勤分结算.
     * 
     * @author 28youth
     * @param  array $user
     * @param  integer $point
     */
    public function createPointLog($user, $point)
    {
        $model = new PointLogModel();
        $model->title = '学历分结算';
        $model->staff_sn = $user['staff_sn'];
        $model->staff_name = $user['realname'];
        $model->brand_id = $user['brand']['id'];
        $model->brand_name = $user['brand']['name'];
        $model->department_id = $user['department_id'];
        $model->department_name = $user['department']['full_name'];
        $model->shop_sn = $user['shop_sn'];
        $model->shop_name = $user['shop']['name'];
        $model->point_a = $point;
        $model->source_id = 3;
        $model->type_id = 0;
        $model->save();
    }

    /**
     * 创建积分日志.
     * 
     * @author 28youth
     * @return ArtisanCommandLog
     */
    public function createLog() : ArtisanCommandLog
    {
        $commandModel = new ArtisanCommandLog();
        $commandModel->command_sn = 'pms:one-time-point-convert';
        $commandModel->created_at = now();
        $commandModel->title = now()->month . '月学历分结算';
        $commandModel->status = 1;

        return $commandModel;
    }
}
