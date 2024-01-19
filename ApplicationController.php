<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationPostRequest;
use App\Models\ApplicationPremise;
use App\Models\CategoryPremise;
use App\Models\Client;
use App\Models\Payment;
use App\Models\RepairPremise;
use App\Models\Status;
use App\Models\TypePremise;
use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApplicationController extends CommonController
{
    public $activeStatus = '';
    protected $entity = 'applications';

    public function __construct()
    {
        parent::__construct();


        $this->middleware(function ($request, $next) {
            \Illuminate\Support\Facades\View::share('entity', $this->entity);
            $this
                ->setCollect([
                    'titleIndex' => config('app.entity.'.$this->entity.'.title_index'),
//                    'notifications' => $this->notificationNewApplication(),
                ]);


            return $next($request);
        });
    }

    public function __invoke(Request $request, Application $application)
    {
        $this->setCommonData($application);
        $filter = $this->getCollect('filter');

        $quantity_item = $filter['quantity'] ? $filter['quantity']: 10 ;

        $models = $application->filtering();

        if ($request->get('status')) {
            $this->activeStatus = $request->get('status');
            $this->count = count($models->get());
            $models = $models
                ->with(['client', 'payment', 'status', 'premises'])
                ->where('status_id', $request->get('status'))
                ->orderBy('id', 'desc')
                ->paginate($quantity_item)
                ->onEachSide(2);
        } else {
            $this->count = count($models->get());
            $models = $models
                ->with(['client', 'payment', 'status', 'premises'])
                ->orderBy('id', 'desc')
                ->paginate($quantity_item)
                ->onEachSide(2);
        }


        $this
            ->setCollect([
                'models' => $models,
                'redirectRouteName' => __FUNCTION__,
                'category_premise'  => TypePremise::where('is_active', 1)->get(),
                'activeStatus' => $this->activeStatus,
                'statuses' => Status::make()->where('is_active', 1)->get(),
                'count' => $this->count,
            ]);
        return view("index", $this->getCollect());
    }

    public function all(Request $request)
    {
        $title = 'Все типы';
        $this->setCollect($this->calendar($request, $title));
        $this->setCollect(['title' => $title]);
        return view("applications.index", $this->getCollect());
    }

    public function gazebos(Request $request)
    {
        $title = 'Беседки';
        $this->setCollect($this->calendar($request, $title));
        $this->setCollect(['title' => $title]);
        return view("applications.index", $this->getCollect());
    }

    public function rooms(Request $request)
    {
        $title = 'Номера';
        $this->setCollect($this->calendar($request, $title));
        $this->setCollect(['title' => $title]);
        return view("applications.index", $this->getCollect());
    }

    public function calendar($request, $title, $period = 'week')
    {

        $delta = $request->get('delta') ? $request->get('delta') : 0;
        $type = config("app.config.type.$title");
        $currentDate = Carbon::now()->format('d.m.Y');
        $now = Carbon::now();

        if($period == 'month')
        {
            $now->startOfMonth()->addMonth($delta);
            $start_period = $now->startOfMonth()->format('Y-m-d');
            $end_period = $now->startOfMonth()->addMonth()->format('Y-m-d');
            $now->startOfMonth()->format('d');
            $weekDateNext = Carbon::parse($start_period)->translatedFormat('d M')
                . ' - ' .
                Carbon::parse($start_period)->addMonth()->subDay()->translatedFormat('d M Y') . ' г.';
            $count_days_table = Carbon::parse($start_period)->diffInDays($end_period);

        } else {
            $now->startOfWeek()->addWeeks($delta);
            $start_period = $now->startOfWeek()->format('Y-m-d');
            $end_period = $now->startOfWeek()->addWeek()->format('Y-m-d');
            $now->startOfWeek()->format('d');
            $weekDateNext = Carbon::parse($start_period)->translatedFormat('d M')
                . ' - ' .
                Carbon::parse($start_period)->addWeek()->subDay()->translatedFormat('d M Y') . ' г.';
            $count_days_table = 7;
        }


        // Получение массива дат для отображения недели
        $dates = [];
        for ($i = 0; $i < Carbon::parse($start_period)->diffInDays($end_period); $i++) {
            $dates[] = [
                'date' => Carbon::parse($start_period)->addDays($i)->format('d.m.Y'),
                'date_top' => Carbon::parse($start_period)->addDays($i)->translatedFormat('d D'),
                'v' => Carbon::parse($start_period)->addDays($i)->isWeekend()
            ];
        }


        $models = Application::make()
            ->with(['premises', 'client'])
            ->where('is_active', '1')
            ->where('status_id', '!=', 4)
            ->where('check_in', '>=', $start_period)
            ->where('check_in', '<=', $end_period)
            ->orWhere('status_id', '!=', 4)
            ->where('is_active', '1')
            ->where('check_out', '<=', $end_period)
            ->where('check_out', '>=', $start_period)
            ->get();

        $repairs = RepairPremise::make()
            ->where('is_active', '1')
            ->where('check_in', '>=', $start_period)
            ->where('check_in', '<=', $end_period)
            ->orWhere('is_active', '1')
            ->where('check_out', '<=', $end_period)
            ->where('check_out', '>=', $start_period)
            ->get();


        $categories = CategoryPremise::make()
            ->with(['premises'])
            ->where('is_active',1)
            ->whereHas('premises', function ($query) use ($type) {
                $query
                    ->where('is_active', 1)
                    ->whereIN('type_premise_id', $type)
                ;
            })
            ->orderBy('position')
            ->get()
        ;

        return [
            'models' => $models,
            'dates' => $dates,
            'delta' => $delta,
            'currentDate' => $currentDate,
            'weekDateNext' => $weekDateNext,
            'categories' => $categories,
            'repairs' => $repairs,
            'count_days_table' => $count_days_table,
        ];
    }

    public function create(CategoryPremise $categories, Status $status, Request $request)
    {
        $type = $request->get('type');
        $this->setCollect([
            'categories' => $categories
                ->where('is_active', 1)
                ->whereHas('premises', function ($query) use ($type) {
                    $query
                        ->where('is_active', 1)
                        ->where('type_premise_id', $type)
                    ;
                })
                ->get(),
            'statused' => $status->where('is_active', 1)->get(),
            'type'  =>  $type
        ]);
        return view("applications.create", $this->getCollect());
    }

    public function store(ApplicationPostRequest $request)
    {
        $requestData = $request->all();
        $client = Client::updateOrCreate(
            [
                'phone' => $requestData['phone']
            ],
            [
                'name' => $requestData['name'],
                'phone' => $requestData['phone']
            ]);

        if($requestData['total'] === $requestData['pay']){
            $paymentId = Payment::select('id')->where('name', 'Оплачено')->first();
        }elseif($requestData['total'] > $requestData['pay'] && $requestData['pay'] != 0){
            $paymentId = Payment::select('id')->where('name', 'Частичная')->first();
        }else{
            $paymentId = Payment::select('id')->where('name', 'Нет')->first();
            $requestData['pay'] = 0;
        }

        if($requestData['check-in'] === null){
            $requestData['check-in'] = Carbon::now()->format('d.m.Y');
        }

        if($requestData['check-out'] === null){
            $dt = Carbon::parse($requestData['check-in']);
            $requestData['check-out'] = $dt->add(1, 'day')->format('d-m-Y');
        }

        $application = new Application([
            'client_id' => $client->id,
            'adult' => $requestData['adult'],
            'type_premises_id' => $requestData['type_premises_id'],
            'child' => $requestData['child'],
            'comment' => $requestData['comment'],
            'total' => $requestData['total'],
            'pay' => $requestData['pay'],
            'check_in' => $requestData['check-in'],
            'check_out' => $requestData['check-out'],
            'payment_id' => $paymentId->id
        ]);
        $application->save();

        foreach ($requestData["rents"] as $rent) {
            if($rent['premise']){
                $application_premise = new ApplicationPremise([
                    'application_id' => $application->id,
                    'premise_id' => $rent['premise'],
                ]);
                $application_premise->save();
            }
        }

        return redirect($requestData['prev_url']);
    }

    public function show(string $id)
    {
        $application = Application::make()
            ->with(['premises'])
            ->where('id', $id)
            ->first();

        $this->setCollect([
            'categories' => CategoryPremise::where('is_active', 1)->get(),
            'application' => $application,
            'statused' => Status::where('is_active', 1)->get(),
        ]);
        return view("applications.edit", $this->getCollect());
    }

    public function update(ApplicationPostRequest $request, $id)
    {
        $requestData = $request->all();
        $client = Client::updateOrCreate([
            'phone' => $requestData['phone']
        ], [
            'name' => $requestData['name'],
            'phone' => $requestData['phone']
        ]);


        if($requestData['total'] > $requestData['pay'] && $requestData['pay'] != 0){
            $payment = Payment::where('name', 'Частичная')->first();
        }elseif($requestData['total'] === $requestData['pay']){
            $payment = Payment::where('name', "Оплачено")->first();
        }else{
            $payment = Payment::where('name', 'Нет')->first();
            $requestData['pay'] = 0;
        }

        //Application
        Application::where('id', $id)
            ->update([
                'client_id' => $client->id,
                'adult' => $requestData['adult'],
                'type_premises_id' => $requestData['type_premises_id'],
                'child' => $requestData['child'],
                'comment' => $requestData['comment'],
                'total' => $requestData['total'],
                'pay' => $requestData['pay'],
                'check_in' => Carbon::parse($requestData['check-in'])->format('Y-m-d'),
                'check_out' => Carbon::parse($requestData['check-out'])->format('Y-m-d'),
                'payment_id' => $payment->id,
                'status_id' => $requestData['status_id'],
        ]);

        ApplicationPremise::where('application_id', $id)->delete();

        foreach ($requestData["rents"] as $rent)
        {
            if($rent['premise']){
                ApplicationPremise::create([
                    'application_id' => $id,
                    'premise_id' => $rent['premise'],
                ]);
            }
        }
        return redirect('/');
    }

    public function updateStatus(Request $request, $id)
    {
        $status = Status::where('name', $request->get('status_name'))->first();
        if ($status) {
            $application = Application::find($id);
            $application->status_id = $status->id;
            $application->save();
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false, 'message' => 'Статус не найден']);
        }
    }

    public function zipsAll(Request $request)
    {
        $orders = $request->get('orders');
        if($orders){
            foreach($orders as $order){
                $application = Application::find($order);
                if ($application->status_id != config("app.config.type.status.zip")) {
                    $application->status_id = 4;
                    $application->save();

                    // Если требуется отправить ответ в формате JSON
                    return response()->json(['success' => true]);
                } else {
                    // Если статус с именем "Архив" не найден
                    return response()->json(['success' => false, 'message' => 'Статус не найден']);
                }
            }
        }
    }

}
