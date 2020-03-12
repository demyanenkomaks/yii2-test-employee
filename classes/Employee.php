<?php

namespace app\classes;

use DiDom\Document;
use Yii;
use Faker;
use yii\db\Exception;

final class Employee
{
    /**
     * Константы названия таблиц
     */
    const DB_WORKER = '{{%worker}}';
    const DB_CABINET = '{{%cabinet}}';
    const DB_WORKER_CABINET = '{{%worker_cabinet}}';

    function __construct() {
        $this->_create();
        $this->_fill();
    }

    /**
     * _create()
     * Добавление таблиц я бы делал бы через миграции (Yii2 и Laravel)
     */
    private function _create()
    {
        $this->createTableWorker();
        $this->createTableCabinet();
        $this->createTableWorkerCabinet();
    }

    /**
     * Добавление таблицы "worker"
     */
    private function createTableWorker()
    {
        if (Yii::$app->db->schema->getTableSchema(self::DB_WORKER, true) === null) {
            Yii::$app->db->createCommand()
                ->createTable(self::DB_WORKER, [
                    'id' => 'pk',
                    'name' => 'string',
                    'tel' => 'string',
                    'address' => 'string',
                    'salary' => 'double',
                    'vkld' => 'string',
                    'photo' => 'string',
                ])
                ->execute();
        }
    }

    /**
     * Добавление таблицы "cabinet"
     */
    private function createTableCabinet()
    {
        if (Yii::$app->db->schema->getTableSchema(self::DB_CABINET, true) === null) {
            Yii::$app->db->createCommand()
                ->createTable(self::DB_CABINET, [
                    'id' => 'pk',
                    'num' => 'integer',
                    'floor' => 'integer',
                    'capacity' => 'integer',
                ])
                ->execute();
        }
    }

    /**
     * Добавление таблицы "worker_cabinet"
     */
    private function createTableWorkerCabinet()
    {
        if (Yii::$app->db->schema->getTableSchema(self::DB_WORKER_CABINET, true) === null) {
            Yii::$app->db->createCommand()
                ->createTable(self::DB_WORKER_CABINET, [
                    'id' => 'pk',
                    'workerld' => 'integer',
                    'cabinetld' => 'integer',
                ])
                ->execute();
            Yii::$app->db->createCommand()
                ->createIndex(
                    'idx-worker-workerld',
                    self::DB_WORKER_CABINET,
                    'workerld'
                )
                ->execute();
            Yii::$app->db->createCommand()
                ->addForeignKey(
                    'fk-worker-workerld',
                    self::DB_WORKER_CABINET,
                    'workerld',
                    self::DB_WORKER,
                    'id',
                    'CASCADE'
                )
                ->execute();
            Yii::$app->db->createCommand()
                ->createIndex(
                    'idx-cabinet-workerld',
                    self::DB_WORKER_CABINET,
                    'cabinetld'
                )
                ->execute();
            Yii::$app->db->createCommand()
                ->addForeignKey(
                    'fk-cabinet-cabinetld',
                    self::DB_WORKER_CABINET,
                    'cabinetld',
                    self::DB_CABINET,
                    'id',
                    'CASCADE'
                )
                ->execute();
        }
    }

    /**
     * _fill()
     */
    private function _fill()
    {
        $this->insertCabinet(100);
        $this->insertWorker();
        $this->createCatalog();
    }

    /**
     * Добавление записей в таблицу "cabinet"
     *
     * @param int $count
     * @throws Exception
     */
    private function insertCabinet($count)
    {
        for ($i = 1; $i <= $count; $i++) {
            Yii::$app->db->createCommand()->insert(self::DB_CABINET, [
                'num' => rand(1, 100),
                'floor' => rand(1, 10),
                'capacity' => rand(1, 10),
            ])->execute();
        }
    }

    /**
     * Добавление записей в таблицу "worker" и связующую таблицу "worker_cabinet"
     */
    private function insertWorker()
    {
        $cabinets = Yii::$app->db->createCommand("SELECT * FROM " . self::DB_CABINET)
            ->queryAll();

        foreach ($cabinets as $cabinet) {
            for ($i = 1; $i < $cabinet['capacity']; $i++) {
                $name = Faker\Factory::create()->name;
                $tel = Faker\Factory::create()->phoneNumber;
                $address = Faker\Factory::create()->address;
                $salary = Faker\Factory::create()->randomFloat(2, 1, 100000);
                $vkld = Faker\Factory::create()->unixTime;
                $cabinetld = $cabinet['id'];
                Yii::$app->db->createCommand("
BEGIN;
INSERT
    INTO " . self::DB_WORKER . " (name, tel, address, salary, vkld)
    VALUES('$name', '$tel', '$address', '$salary', '$vkld');
INSERT
    INTO " . self::DB_WORKER_CABINET . " (workerld, cabinetld) 
    VALUES(LAST_INSERT_ID(), '$cabinetld');
COMMIT;
                ")->execute();
            }
        }
    }

    /**
     * Добавление каталога для каждого worker
     */
    private function createCatalog()
    {
        $docs = Yii::getAlias('@app') . '/web/docs/';

        if (!is_dir($docs)) {
            mkdir($docs, 0700);
        }
        $workers = Yii::$app->db->createCommand("SELECT * FROM " . self::DB_WORKER)
            ->queryAll();

        foreach ($workers as $worker) {
            $doc_worker = $docs . $worker['id'];
            if (!is_dir($doc_worker)) {
                mkdir($doc_worker, 0700);
            }
        }
    }

    /**
     * 2.1 Оптимизировать запрос и создать метод, выполняющий выборку: SELECT * FROM worker, cabinet, worker_cabinet WHERE worker_cabinet.workerId = worker.id AND worker_cabinet.cabinetId = cabinet.id;
     */
    public function getAllWorkerCabinet()
    {
        return Yii::$app->db->createCommand("
SELECT *
FROM cabinet AS c
LEFT JOIN worker_cabinet AS wc ON c.id = wc.cabinetld
LEFT JOIN worker AS w ON wc.workerld = w.id
        ")
            ->queryAll();
    }

    /**
     * 2.2 Создать метод выбирающий всех сотрудников на определенном этаже;
     *
     * @param int $floor
     * @return array
     * @throws Exception
     */
    public function getSearchWorkerOnFloor($floor)
    {
        return Yii::$app->db->createCommand("
SELECT * 
FROM worker AS w
LEFT JOIN worker_cabinet AS wc ON w.id = wc.workerld
LEFT JOIN cabinet AS c ON wc.cabinetld = c.id
WHERE c.floor = 1
")
            ->queryAll();
    }

    /**
     * 2.3 Создать метод, выбирающий сотрудников с наибольшей зар. платой на этаже/в кабинете;
     *
     * @return array
     * @throws Exception
     */
    public function getSearchWorkersHighSalary()
    {
        return Yii::$app->db->createCommand("
SELECT floor, MAX(salary), w.*
FROM cabinet AS c
LEFT JOIN worker_cabinet AS wc ON c.id = wc.cabinetld
LEFT JOIN worker AS w ON wc.workerld = w.id
GROUP BY floor
")
            ->queryAll();
    }

    /**
     * 2.4 Создать метод, выбирающий всех сотрудников из кабинета с наибольшей/наименьшей вместимостью;
     *
     * @return array
     * @throws Exception
     */
    public function getSearchWorkersOfCabinetLargest()
    {
        return Yii::$app->db->createCommand("
SELECT * 
FROM worker AS w
LEFT JOIN worker_cabinet AS wc ON w.id = wc.workerld
LEFT JOIN cabinet AS c ON wc.cabinetld = c.id
WHERE c.capacity in (
	SELECT MAX(capacity)
    FROM cabinet
)")
            ->queryAll();
    }

    /**
     * 2.5 Создать метод, который в каталоге /docs/<worker.id> находит все файлы, имена которых
     * состоят из цифр и букв латинского алфавита, имеют расширение txt и выводит на экран
     * имена этих файлов, упорядоченных по имени. Каталог выбирать по полю name из
     * таблицы worker. Задачу выполнить с применением регулярных выражений;
     *
     * @return array
     *
     * Выполнено с заходом в каждый каталог /docs/<worker.id> и проверяет все файлы, не через базу
     */
    public function getSearchFile()
    {
        $result = [];
        $path = Yii::getAlias('@app') . '/web/docs/';

        $docs = scandir($path, 1);
        foreach ($docs as $doc) {
            if ($doc !== '..' || $doc !== '.') {
                $worker_docs = scandir($path . '/' . $doc . '/', 1);
                foreach ($worker_docs as $wd) {
                    if (preg_match('/(^[a-zA-Z0-9]+([a-zA-Z\_0-9\.-]*)).txt/', $wd)) {
                        array_push($result, $wd);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 2.6 Создать метод, получающий фото со страницы Вконтакте сотрудника (по полю
     * worker.vkId) и сохраняющий ссылку на фото в поле worker.photo.
     *
     * @param int $id
     * @return int
     *
     * @throws Exception
     *
     * Для теста брался vkId = smorkovkina
     */

    public function getSearchPhotoVk($id)
    {
        $worker = Yii::$app->db->createCommand("SELECT * FROM worker WHERE id = $id")
            ->queryOne();

        $document = new Document('https://vk.com/' . $worker['vkld'], true);
        $link = $document->find('a[href]:has(img)::attr(href)');

        $document = new Document('https://vk.com' . $link[0], true);
        $img_link = $document->find('img::attr(src)');

        return Yii::$app->db->createCommand()->update(
            'worker',
            ['photo' => $img_link[0]],
            'id = ' . $id
            )->execute();
    }
}

