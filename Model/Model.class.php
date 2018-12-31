<?php
class Model
{
    protected $dsn;
    protected $user;
    protected $password;
    protected $table;
    protected $pdo;
    protected $dbNum;

    public $errCode;
    public $errMsg;
    public function __construct()
    {
        if (!$this->pdo) {
            $this->dbConnect();
        }
    }

    public function table($tableName)
    {
        $this->table = $tableName;
        return $this;
    }

    protected function dbConnect()
    {
        $dbConfig = getConf("db");
        if (!$dbConfig) {
            $this->errMsg = json_encode('数据库未配置');
            return false;
        }
        $this->dsn = "mysql:dbname={$dbConfig['dbname']};host={$dbConfig['host']};port={$dbConfig['port']}";
        $this->user = $dbConfig['user'];
        $this->password = $dbConfig['password'];
        try {
            $this->pdo = new PDO($this->dsn, $this->user, $this->password);
            $this->pdo->query('set names utf8');
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
        if (!$result) {
            //errLog
            return false;
        }
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
                $ret = (int)$this->execute($sql, $param);
                if (!$ret) {
                    return false;
                }
            }
        }
        return true;
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
        $ret = $this->execute($sql, $param);
        if (!$ret) {
            return false;
        }
        return $ret->rowCount();
    }

    //更新数据
    public function updateData($data, $where)
    {
        $table = $this->table;
        $param = array();
        $sql = "UPDATE {$table} SET ";
        foreach ($data as $key => $value) {
            $sql .= "{$key}=:{$key},";
            $param[":{$key}"] = $value;
        }
        $sql = rtrim($sql, ',');
        $sql .= " WHERE {$where}";
        $ret = $this->execute($sql, $param);
        if (!$ret) {
            return false;
        }
        return $ret->rowCount();
    }

    //查找数据列表
    public function getDataList($select = '*', $where = 1, $param = [], $order = '', $limit = -1)
    {
        $table = $this->table;
        if(!is_string($select)) {
            return false;
        }
        $sql = "SELECT {$select} FROM {$table} WHERE {$where}";
        if ($order) {
            $sql .= " ORDER BY {$order}";
        }
        if ($limit != -1) {
            $sql .= " LIMIT {$limit}";
        }
        $ret = $this->execute($sql, $param);
        if (!$ret) {
            return false;
        }
        return $ret->fetchAll(PDO::FETCH_ASSOC);
    }
    //获取一行数据
    public function getDataDetail($select = '*', $where = 1, $param = [], $order = '')
    {
        $table = $this->table;
        if(!is_string($select)) {
            return false;
        }
        $sql = "SELECT {$select} FROM {$table} WHERE {$where}";
        if ($order) {
            $sql .= " ORDER BY {$order}";
        }
        $sql .= " LIMIT 1";
        $ret = $this->execute($sql, $param);
        if (!$ret) {
            return false;
        }
        return $ret->fetch(PDO::FETCH_ASSOC);
    }
}