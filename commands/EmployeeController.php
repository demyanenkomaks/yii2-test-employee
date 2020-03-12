<?php
namespace app\commands;

use app\classes\Employee;
use yii\console\Controller;

class EmployeeController extends Controller
{
    public function actionIndex()
    {
        $model = Employee::getSearchWorkersHighSalary();

        print_r($model);
        echo "\n" . 'true !!!' . "\n";
    }
}