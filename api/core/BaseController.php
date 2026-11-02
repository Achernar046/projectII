<?php
/**
 * api/core/BaseController.php
 * Abstract Base Controller for RESTful API Controllers
 */

abstract class BaseController
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get parsed JSON input body or POST array
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST ?? [];
    }

    /**
     * Validate data fields against simple rules
     * Example rules: ['c_ProductName' => 'required', 'i_Price' => 'required|numeric']
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $val = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required') {
                    if ($val === null || (is_string($val) && trim($val) === '')) {
                        $errors[$field][] = "ฟิลด์ {$field} จำเป็นต้องระบุข้อมูล";
                    }
                } elseif ($rule === 'numeric') {
                    if ($val !== null && trim((string)$val) !== '' && !is_numeric($val)) {
                        $errors[$field][] = "ฟิลด์ {$field} ต้องเป็นตัวเลข";
                    }
                } elseif ($rule === 'integer') {
                    if ($val !== null && trim((string)$val) !== '' && filter_var($val, FILTER_VALIDATE_INT) === false) {
                        $errors[$field][] = "ฟิลด์ {$field} ต้องเป็นเลขจำนวนเต็ม";
                    }
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (float)substr($rule, 4);
                    if (is_numeric($val) && (float)$val < $min) {
                        $errors[$field][] = "ฟิลด์ {$field} ต้องมีค่าอย่างน้อย {$min}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Helper to get pagination parameters
     */
    protected function getPaginationParams(int $defaultLimit = 10): array
    {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : $defaultLimit;
        $offset = ($page - 1) * $limit;

        return [$page, $limit, $offset];
    }
}
