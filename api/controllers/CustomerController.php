<?php
/**
 * api/controllers/CustomerController.php
 * RESTful CRUD Controller for Customers (Screen 8)
 */

require_once __DIR__ . '/../core/BaseController.php';

class CustomerController extends BaseController
{
    /**
     * List customers with search and pagination
     * GET /api/customers
     */
    public function index(): void
    {
        [$page, $limit, $offset] = $this->getPaginationParams(10);
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $whereConditions = [];
        $params = [];

        if ($search !== '') {
            $whereConditions[] = "(c_customername LIKE :search OR c_contactname LIKE :search OR c_city LIKE :search OR c_country LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Total count
        $countSql = "SELECT COUNT(*) FROM tb_customers {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        // Query customers
        $sql = "SELECT c.i_customerid, c.c_customername, c.c_contactname, c.c_address, c.c_city, c.c_postalcode, c.c_country,
                       COUNT(o.i_OrderID) AS order_count
                FROM tb_customers c
                LEFT JOIN tb_orders o ON c.i_customerid = o.i_CustomerID
                {$whereSql}
                GROUP BY c.i_customerid, c.c_customername, c.c_contactname, c.c_address, c.c_city, c.c_postalcode, c.c_country
                ORDER BY c.i_customerid ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $customers = $stmt->fetchAll();

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        Response::success('ดึงรายการลูกค้าสำเร็จ', $customers, [
            'current_page'  => $page,
            'per_page'      => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages
        ]);
    }

    /**
     * Get single customer by ID
     * GET /api/customers/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;
        $sql = "SELECT * FROM tb_customers WHERE i_customerid = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch();

        if (!$customer) {
            Response::notFound("ไม่พบข้อมูลลูกค้า #{$id}");
        }

        // Fetch recent orders
        $orderSql = "SELECT o.i_OrderID, o.c_OrderDate, s.c_ShipperName,
                            COALESCE(SUM(od.i_Quantity * p.i_Price), 0) AS total_amount
                     FROM tb_orders o
                     LEFT JOIN tb_shippers s ON o.i_ShipperID = s.i_ShipperID
                     LEFT JOIN tb_orderdetails od ON o.i_OrderID = od.i_OrderID
                     LEFT JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                     WHERE o.i_CustomerID = :id
                     GROUP BY o.i_OrderID, o.c_OrderDate, s.c_ShipperName
                     ORDER BY o.i_OrderID DESC
                     LIMIT 5";
        $orderStmt = $this->db->prepare($orderSql);
        $orderStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $orderStmt->execute();
        $customer['recent_orders'] = $orderStmt->fetchAll();

        Response::success("ดึงข้อมูลลูกค้า #{$id} สำเร็จ", $customer);
    }

    /**
     * Create a new customer
     * POST /api/customers
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_customername' => 'required',
            'c_contactname'  => 'required',
            'c_country'      => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "INSERT INTO tb_customers (c_customername, c_contactname, c_address, c_city, c_postalcode, c_country)
                VALUES (:name, :contact, :address, :city, :postal, :country)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_customername']), PDO::PARAM_STR);
        $stmt->bindValue(':contact', trim($body['c_contactname']), PDO::PARAM_STR);
        $stmt->bindValue(':address', trim($body['c_address'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':city', trim($body['c_city'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':postal', trim($body['c_postalcode'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':country', trim($body['c_country']), PDO::PARAM_STR);
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId();
        $this->show($newId);
    }

    /**
     * Update customer
     * PUT /api/customers/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        $check = $this->db->prepare("SELECT i_customerid FROM tb_customers WHERE i_customerid = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบข้อมูลลูกค้า #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_customername' => 'required',
            'c_contactname'  => 'required',
            'c_country'      => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "UPDATE tb_customers 
                SET c_customername = :name,
                    c_contactname  = :contact,
                    c_address      = :address,
                    c_city         = :city,
                    c_postalcode   = :postal,
                    c_country      = :country
                WHERE i_customerid = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_customername']), PDO::PARAM_STR);
        $stmt->bindValue(':contact', trim($body['c_contactname']), PDO::PARAM_STR);
        $stmt->bindValue(':address', trim($body['c_address'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':city', trim($body['c_city'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':postal', trim($body['c_postalcode'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':country', trim($body['c_country']), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete customer
     * DELETE /api/customers/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        // Check if customer has orders
        $checkOrders = $this->db->prepare("SELECT COUNT(*) FROM tb_orders WHERE i_CustomerID = :id");
        $checkOrders->bindParam(':id', $id, PDO::PARAM_INT);
        $checkOrders->execute();
        $orderCount = (int)$checkOrders->fetchColumn();

        if ($orderCount > 0) {
            Response::error("ไม่สามารถลบลูกค้ารายนี้ได้เนื่องจากมีประวัติการสั่งซื้อ {$orderCount} ออร์เดอร์", 409);
        }

        $sql = "DELETE FROM tb_customers WHERE i_customerid = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบข้อมูลลูกค้า #{$id}");
        }

        Response::success("ลบข้อมูลลูกค้า #{$id} เรียบร้อยแล้ว");
    }
}
