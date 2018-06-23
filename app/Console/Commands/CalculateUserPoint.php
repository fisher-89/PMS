<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use function App\monthBetween;
use App\Models\PointLogSource;
use App\Models\PointLog as PointLogModel;
use App\Models\ArtisanCommandLog as CommandLogModel;
use App\Models\PersonalPointStatistic as StatisticModel;
use App\Models\PersonalPointStatisticLog as StatisticLogModel;

class CalculateUserPoint extends Command
{

    protected $signature = 'pms:calculate-user-point';
    protected $description = 'Calculate user point';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
    	$this->calculateMonthPoint();
    }

    public function calculateBasePoint()
    {
        $lastMonth = [];
        $statistics = [];
        $calculatedAt = Carbon::now();
        $lastDaily = CommandLogModel::bySn('pms:calculate-user-point')->latest('id')->first();
        if ($lastDaily !== null) {

            // 获取上次结算的所有数据
            $lastStatis = StatisticModel::query()
                ->select('staff_sn', 'point_a', 'point_b_monthly', 'point_b_total', 'source_b_monthly', 'source_b_total')
                ->whereBetween('calculated_at', [$lastDaily->created_at, $calculatedAt])
                ->get();
            
            $lastStatis->map(function ($item) use (&$statistics, &$lastMonthly, $lastDaily) {

                // 判断跨月清空数据
                if (!Carbon::parse($lastDaily->created_at)->isCurrentMonth()) {
                    // 生成上月月结数据
                    $lastMonthly[$item->staff_sn] = $item->toArray();
                    $lastMonthly[$item->staff_sn]['date'] = Carbon::create(null, null, 02)->subMonth();
                    
                    $item->point_a = 0;
                    $item->point_b_monthly = 0;
                    $item->source_b_monthly = PointLogSource::get();
                }
                $statistics[$item->staff_sn] = $item->toArray();
            });
        }

        // 拿上次日结到现在的积分日志
        $logs = PointLogModel::query()
            ->select('point_a', 'point_b', 'staff_sn', 'source_id', 'changed_at')
            ->when($lastDaily, function ($query) use ($lastDaily, $calculatedAt) {
                $query->whereBetween('created_at', [$lastDaily->created_at, $calculatedAt]);
            })
            ->get();
        // 统计每个员工的积分
        foreach ($logs as $key => $log) {
            // 判断是否有上月的记录
            if (!Carbon::parse($log->changed_at)->isCurrentMonth()) {
                if (isset($lastMonth[$log->staff_sn])) {
                    $lastMonth[$log->staff_sn]['point_a'] += $log->point_a;
                    $lastMonth[$log->staff_sn]['point_b_monthly'] += $log->point_b;
                    $lastMonth[$log->staff_sn]['point_b_total'] += $log->point_b;
                    $lastMonth[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, $lastMonth);
                    $lastMonth[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, $lastMonth, 'total');
                } else {
                    $lastMonth[$log->staff_sn]['point_a'] = $log->point_a;
                    $lastMonth[$log->staff_sn]['point_b_monthly'] = $log->point_b;
                    $lastMonth[$log->staff_sn]['point_b_total'] = $log->point_b;
                    $lastMonth[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, $lastMonth);
                    $lastMonth[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, $lastMonth, 'total');
                    $lastMonth[$log->staff_sn]['date'] = Carbon::create(null, null, 02)->subMonth();
                }
            }

            if (isset($statistics[$log->staff_sn])) {
                $statistics[$log->staff_sn]['point_a'] += $log->point_a;
                $statistics[$log->staff_sn]['point_b_monthly'] += $log->point_b;
                $statistics[$log->staff_sn]['point_b_total'] += $log->point_b;
                $statistics[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, $statistics);
                $statistics[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, $statistics, 'total');
                $statistics[$log->staff_sn]['calculated_at'] = $calculatedAt;

                continue;
            }
            $statistics[$log->staff_sn]['point_a'] = $log->point_a;
            $statistics[$log->staff_sn]['point_b_monthly'] = $log->point_b;
            $statistics[$log->staff_sn]['point_b_total'] = $log->point_b;
            $statistics[$log->staff_sn]['source_b_monthly'] = $this->monthlySource($log, $statistics);
            $statistics[$log->staff_sn]['source_b_total'] = $this->monthlySource($log, $statistics, 'total');
            $statistics[$log->staff_sn]['calculated_at'] = $calculatedAt;

        }
        $commandID = CommandLogModel::insertGetId([
            'command_sn' => 'pms:calculate-user-point',
            'title' => '积分结算',
            'status' => 0,
            'created_at' => $calculatedAt
        ]);

        try {
            \DB::beginTransaction();

            array_walk($statistics, [$this, 'saveDailyLog']);
            array_walk($lastMonth, [$this, 'saveMonthlyLog']);

            CommandLogModel::where('id', $commandID)->update([
                'status' => 1
            ]);

            \DB::commit();
        } catch (Exception $e) {

            CommandLogModel::where('id', $commandID)->update([
                'status' => 2
            ]);

            \DB::rollBack();
        }
    }
    
    /**
     * 获取结算所需用户信息.
     * 
     * @author 28youth
     * @return array
     */
    public function checkStaff(int $staff_sn): array
    {
        $user = app('api')->getStaff($staff_sn);

        return [
            'staff_sn' => $user['staff_sn'] ?? 0,
            'staff_name' => $user['realname'] ?? '',
            'brand_id' => $user['brand']['id'] ?? 0,
            'brand_name' => $user['brand']['name'] ?? '',
            'department_id' => $user['department']['id'] ?? 0,
            'department_name' => $user['department']['full_name'] ?? '',
            'shop_sn' => $user['shop']['shop_sn'] ?? '',
            'shop_name' => $user['shop']['shop_name'] ?? '',
        ];
    }

    /**
     * 来源积分统计.
     * 
     * @author 28youth
     * @return array
     */
    public function monthlySource($log, $statistic, $type = 'monthly')
    {
        $statistic = $statistic[$log->staff_sn]['source_b_monthly'] ?? PointLogSource::get()->toArray();
        if ($type === 'total') {
            $statistic = $statistic[$log->staff_sn]['source_b_total'] ?? PointLogSource::get()->toArray();
        }

        foreach ($statistic as $key => &$value) {
            // 初始化值
            $value['add_point'] = $value['add_point'] ?? 0;
            $value['sub_point'] = $value['sub_point'] ?? 0;
            $value['point_b_total'] = $value['point_b_total'] ?? 0;

            if ($value['id'] === $log->source_id) {
                $value['point_b_total'] += $log->point_b;
                if ($log->point_b >= 0) {
                    $value['add_point'] += $log->point_b;
                } else {
                    $value['sub_point'] += $log->point_b;
                }
            }
        }

        return $statistic;
    }

    // 更新日结数据
    public function saveDailyLog( $statistic, $key )
    {
        $staff = $this->checkStaff($key);

        $logModel = StatisticModel::where('staff_sn', $key)->first();
        if ($logModel === null) {
            $logModel = new StatisticModel();
        }
        $logModel->fill($staff + $statistic);
        $logModel->save();
    }

    // 更新月结数据
    public function saveMonthlyLog($statistic, $key)
    {
        $staff = $this->checkStaff($key);

        $logModel = StatisticLogModel::where('staff_sn', $key)
            ->whereBetween('date', monthBetween($statistic['date']))
            ->first();
        if ($logModel === null) {
            $logModel = new StatisticLogModel();
        }
        $logModel->fill($staff + $statistic);
        $logModel->save();
    }
}
