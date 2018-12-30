<?php
class Model
{
    protected $dsn;
    protected $user;
    protected $password;
    protected $table;
    protected $pdo;
    protected $pdoArr;
    protected $dbNum;

    public $errCode;
    public $errMsg;
    public function __construct()
    {
        if (!isset($this->pdoArr[$this->dbNum]) || !$this->pdoArr[$this->dbNum]) {
            $this->dbConnect($this->dbNum);
        }
        $this->pdo = $this->pdoArr[$this->dbNum];
    }

    protected function dbConnect($dbNum = 0)
    {
        $dbConfig = getConf("db.{$dbNum}");
        if (!$dbConfig) {
            $this->errMsg = json_encode('数据库未配置');
            return false;
        }
        $this->dsn = "mysql:dbname={$dbConfig['dbname']};host={$dbConfig['host']};port={$dbConfig['port']}";
        $this->user = $dbConfig['user'];
        $this->password = $dbConfig['password'];
        try {
            $this->pdo[$dbNum] = new PDO($this->dsn, $this->user, $this->password);
            $this->pdo[$dbNum]->query('set names utf8');
            return true;
        } catch (Exception $e) {
            $this->errMsg = json_encode($e);
            return false;
        }
    }

    public function execute($sql, $param = [])
    {
        $statement = $this->pdo->prepare($sql);
        $result = $statement->execute($param);
        return $statement;
    }
    //多行插入
    public function multipleInsert($data)
    {
        $table = $this->table;
        if (!isset($data[0]) || !is_array($data[0]) || !$data[0]) {
            return false;
        }
        $result = 0;
        $column = array_keys($data[0]);
        $commonSql = "INSERT INTO {$table}(";
        foreach ($column as $value) {
            $commonSql .= '`' . $value . '`,';
        }
        $commonSql = rtrim($commonSql, ',');
        $commonSql .= ') ';
        $count = count($data);
        $length = 25;
        for ($index = 0; $index < $count; $index += 25)
        {
            $index + $length > $count && $length = $count - $index;
            $tempData = array_slice($data, $index, $length);
            if ($tempData) {
                $param = [];
                $sql = $commonSql . 'VALUES';
                $order = 0;
                foreach ($tempData as $key => $item) {
                    $temp = '(:' . implode("{$order},:", $column);
                    $temp .= "{$order}),";
                    foreach ($column as $value) {
                        $param[":{$value}{$order}"] = $item[$value];
                    }
                    $sql .= $temp;
                    $order++;
                }
                $sql = rtrim($sql, ',');
                //影响行数
                $tempCount = (int)$this->execute($sql, $param)->rowCount();
                $result += $tempCount;
            }
        }
        //最新id
        // $lastId = $this->getLastId() + $tempCount - 2;
        return $result;
    }
    //插入一条数据
    public function insertData($data)
    {
        $table = $this->table;
        if (!is_array($data) || !$data) {
            return false;
        }
        $sql = "INSERT INTO {$table}(";
        $value = "VALUES(";
        $param = [];
        foreach ($data as $key => $item) {
            $sql .= "{$key},";
            $value .= ":{$key},";
            $param[":{$key}"] = $item;
        }
        $sql = rtrim($sql, ',');
        $sql .= ')';
        $sql .= rtrim($value, ',');
        $sql .= ');';
        return $this->execute($sql, $param)->rowCount();
    }
    //更新数据
    public function updateData($data, $where, $param)
    {
        $table = $this->table;
        $sql = "UPDATE {$table} SET ";
        foreach ($data as $key => $value) {
            $sql .= "{$key}=:{$key},";
            $param[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ',');
        $sql .= " WHERE {$where}";
        return $this->execute($sql, $param)->rowCount();
    }
    //返回最后一行的信息
    public function getLastId()
    {
        return $this->pdo->lastInsertId();
    }
    //通过id更新数据
    public function updateDataById($data, $id)
    {
        $table = $this->table;
        $where = "id=:id";
        $param = [':id' => $id];
        return $this->updateData($table, $data, $where, $param);
    }
    //查找数据列表
    public function getDataList($select = '*', $where = 1, $param = [], $order = 'id desc', $limit = 20)
    {
        $table = $this->table;
        if(!is_string($select)) {
            return [];
        }
        $sql = "SELECT {$select} FROM {$table} WHERE {$where} ORDER BY {$order}";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        return $this->execute($sql, $param)->fetchAll(PDO::FETCH_ASSOC);
    }
    //获取一行数据
    public function getDataDetail($select = '*', $where = 1, $param = [], $order = 'id desc')
    {
        $table = $this->table;
        if(!is_string($select)) {
            return [];
        }
        $sql = "SELECT {$select} FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT 1";
        return $this->execute($sql, $param)->fetch(PDO::FETCH_ASSOC);
    }
}