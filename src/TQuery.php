<?php
require_once  __DIR__.'/../../../../lib/adianti/core/AdiantiCoreLoader.php';
spl_autoload_register(array('Adianti\Core\AdiantiCoreLoader', 'autoload'));

/**
 * Undocumented class
 */
class TQuery
{
    private $query_name;
    private $criteria;
    private $base_path_querys;
    private $query_separator;
    private $pdo;
    private $querys;
    private $query;
    private $obj_expected;
    private $params = [];

    /**
     * Método construtor
     *
     * @param string $query_name
     * @param array $params
     * @param string $obj
     */
    public function __construct($query_name, $params = [], $obj = "stdClass")
    {
        $this->query_name = $query_name;
        $this->pdo = TTransaction::get();
        $this->obj_expected = $obj;
        $this->params = $params;
        $this->base_path_querys = __DIR__.'/../../../../'."app/querys/";
    }

    public function setBasePathQuerys(string $base_path_querys){
        $this->base_path_querys = __DIR__.'/../../../../'.$base_path_querys;
    }

    public function setMultiQuerySeparator($separator = ";")
    {
        $this->query_separator = $separator;
    }

    private function buildQuery()
    {
        $query = file_get_contents($this->base_path_query . $this->query_name);

        if ($this->criteria) {
            $query = str_replace("{{WHERE}}", " WHERE " . $this->criteria->dump(), $query);
        } else {
            $query = str_replace("{{WHERE}}", " ", $query);
        }

        if ($this->params) {
            foreach ($this->params as $name => $value) {
                $query = str_replace("{{" . $name . "}}", $value, $query);
            }
        }

        if (!empty($this->query_separator)) {
            $this->querys = explode($this->query_separator, $query);
        } else {
            $this->query = $query;
        }
    }

    public function execute()
    {
        try {

            $this->buildQuery();

            $totalRows = 0;
            foreach ($this->querys as $key => $query) {
                if (!empty(trim($query))) {
                    TTransaction::log($query);
                    $totalRows += $this->pdo->exec($query);
                }
            }
            return $totalRows;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function load(TCriteria $criteria = null)
    {
        $data = [];
        if ($criteria) {
            $this->criteria = $criteria;
        }

        $this->buildQuery();

        if ($this->obj_expected == "stdClass") {
            $data = $this->pdo->query($this->query)->fetchAll(PDO::FETCH_OBJ);
        } else {
            $rows = $this->pdo->query($this->query)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $obj = new $this->obj_expected;
                $obj->fromArray($row);
                $data[] = $obj;
            }
        }
        return $data;
    }

    public function dump(TCriteria $criteria = null)
    {
        $data = [];
        if ($criteria) {
            $this->criteria = $criteria;
        }

        $this->buildQuery();

        return ($this->querys) ? $this->querys : $this->query;
    }

    public function setParams(array $params){
        $this->params = $params;
    }
}
