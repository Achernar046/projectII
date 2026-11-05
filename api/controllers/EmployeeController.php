<?php
/**
 * api/controllers/EmployeeController.php
 * RESTful CRUD Controller for Employees (Screen 10)
 */

require_once __DIR__ . '/../core/BaseController.php';

class EmployeeController extends BaseController
{
    /**
     * List employees with processed order counts
     * GET /api/employees
     */
    public function index(): void
    {
        // The employees screen has no pagination controls, so include the
        // complete practical list in one response and show recently added staff first.
        [$page, $limit, $offset] = $this->getPaginationParams(100);
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $whereConditions = [];
        $params = [];

        if ($search !== '') {
            $whereConditions[] = "(e.c_FirstName LIKE :search_first OR e.c_LastName LIKE :search_last OR e.c_Notes LIKE :search_notes)";
            $searchTerm = "%{$search}%";
            $params[':search_first'] = $searchTerm;
            $params[':search_last'] = $searchTerm;
            $params[':search_notes'] = $searchTerm;
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $countSql = "SELECT COUNT(*) FROM tb_employees e {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        $sql = "SELECT e.i_EmployeeID, e.c_LastName, e.c_FirstName, e.c_BirthDate, e.c_Photo, e.c_Notes,
                       COUNT(o.i_OrderID) AS processed_orders
                FROM tb_employees e
                LEFT JOIN tb_orders o ON e.i_EmployeeID = o.i_EmployeeID
                {$whereSql}
                GROUP BY e.i_EmployeeID, e.c_LastName, e.c_FirstName, e.c_BirthDate, e.c_Photo, e.c_Notes
                ORDER BY e.i_EmployeeID DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $employees = $stmt->fetchAll();

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        Response::success('ดึงรายการพนักงานสำเร็จ', $employees, [
            'current_page'  => $page,
            'per_page'      => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages
        ]);
    }

    /**
     * Get single employee by ID
     * GET /api/employees/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;
        $sql = "SELECT e.*,
                       (SELECT COUNT(*) FROM tb_orders o WHERE o.i_EmployeeID = e.i_EmployeeID) AS processed_orders
                FROM tb_employees e
                WHERE e.i_EmployeeID = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $employee = $stmt->fetch();

        if (!$employee) {
            Response::notFound("ไม่พบข้อมูลพนักงาน #{$id}");
        }

        // Recent orders processed
        $orderSql = "SELECT o.i_OrderID, o.c_OrderDate, c.c_customername
                     FROM tb_orders o
                     LEFT JOIN tb_customers c ON o.i_CustomerID = c.i_customerid
                     WHERE o.i_EmployeeID = :id
                     ORDER BY o.i_OrderID DESC
                     LIMIT 5";
        $orderStmt = $this->db->prepare($orderSql);
        $orderStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $orderStmt->execute();
        $employee['recent_orders'] = $orderStmt->fetchAll();

        Response::success("ดึงข้อมูลพนักงาน #{$id} สำเร็จ", $employee);
    }

    /**
     * Create employee
     * POST /api/employees
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_FirstName' => 'required',
            'c_LastName'  => 'required',
            'c_BirthDate' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "INSERT INTO tb_employees (c_FirstName, c_LastName, c_BirthDate, c_Photo, c_Notes)
                VALUES (:first, :last, :birth, :photo, :notes)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':first', trim($body['c_FirstName']), PDO::PARAM_STR);
        $stmt->bindValue(':last', trim($body['c_LastName']), PDO::PARAM_STR);
        $stmt->bindValue(':birth', trim($body['c_BirthDate']), PDO::PARAM_STR);
        $stmt->bindValue(':photo', trim($body['c_Photo'] ?? 'EmpID1.pic'), PDO::PARAM_STR);
        $stmt->bindValue(':notes', trim($body['c_Notes'] ?? ''), PDO::PARAM_STR);
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId();
        $this->show($newId);
    }

    /**
     * Update employee
     * PUT /api/employees/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        $check = $this->db->prepare("SELECT i_EmployeeID FROM tb_employees WHERE i_EmployeeID = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบข้อมูลพนักงาน #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_FirstName' => 'required',
            'c_LastName'  => 'required',
            'c_BirthDate' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "UPDATE tb_employees
                SET c_FirstName = :first,
                    c_LastName  = :last,
                    c_BirthDate = :birth,
                    c_Photo     = :photo,
                    c_Notes     = :notes
                WHERE i_EmployeeID = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':first', trim($body['c_FirstName']), PDO::PARAM_STR);
        $stmt->bindValue(':last', trim($body['c_LastName']), PDO::PARAM_STR);
        $stmt->bindValue(':birth', trim($body['c_BirthDate']), PDO::PARAM_STR);
        $stmt->bindValue(':photo', trim($body['c_Photo'] ?? 'EmpID1.pic'), PDO::PARAM_STR);
        $stmt->bindValue(':notes', trim($body['c_Notes'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete employee
     * DELETE /api/employees/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        $checkOrders = $this->db->prepare("SELECT COUNT(*) FROM tb_orders WHERE i_EmployeeID = :id");
        $checkOrders->bindParam(':id', $id, PDO::PARAM_INT);
        $checkOrders->execute();
        $orderCount = (int)$checkOrders->fetchColumn();

        if ($orderCount > 0) {
            Response::error("ไม่สามารถลบข้อมูลพนักงานรายนี้ได้เนื่องจากมีคำสั่งซื้อที่ดำเนินการโดยพนักงานนี้ {$orderCount} รายการ", 409);
        }

        $sql = "DELETE FROM tb_employees WHERE i_EmployeeID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบข้อมูลพนักงาน #{$id}");
        }

        Response::success("ลบข้อมูลพนักงาน #{$id} เรียบร้อยแล้ว");
    }
}
