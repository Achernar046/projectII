<?php
/**
 * api/controllers/OrderController.php
 * RESTful CRUD Controller for Orders & Order Details (Screens 6 & 7)
 */

require_once __DIR__ . '/../core/BaseController.php';

class OrderController extends BaseController
{
    /**
     * List orders with customer, employee, shipper, and total amount
     * GET /api/orders
     */
    public function index(): void
    {
        [$page, $limit, $offset] = $this->getPaginationParams(10);

        $search    = isset($_GET['search']) ? trim($_GET['search']) : '';
        $shipperId = isset($_GET['shipper_id']) && is_numeric($_GET['shipper_id']) ? (int)$_GET['shipper_id'] : null;
        $customerId = isset($_GET['customer_id']) && is_numeric($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

        $whereConditions = [];
        $params = [];

        if ($search !== '') {
            $whereConditions[] = "(o.i_OrderID = :search_id OR c.c_customername LIKE :search_name)";
            $params[':search_id']   = is_numeric($search) ? (int)$search : 0;
            $params[':search_name'] = "%{$search}%";
        }

        if ($shipperId !== null) {
            $whereConditions[] = "o.i_ShipperID = :shipper_id";
            $params[':shipper_id'] = $shipperId;
        }

        if ($customerId !== null) {
            $whereConditions[] = "o.i_CustomerID = :customer_id";
            $params[':customer_id'] = $customerId;
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Count Total
        $countSql = "SELECT COUNT(*) 
                     FROM tb_orders o 
                     LEFT JOIN tb_customers c ON o.i_CustomerID = c.i_customerid 
                     {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        // Query Orders with aggregation
        $sql = "SELECT o.i_OrderID, o.i_CustomerID, o.i_EmployeeID, o.c_OrderDate, o.i_ShipperID,
                       c.c_customername, c.c_country,
                       CONCAT(e.c_FirstName, ' ', e.c_LastName) AS employee_name,
                       s.c_ShipperName,
                       COALESCE(SUM(od.i_Quantity * p.i_Price), 0) AS total_amount,
                       COUNT(od.i_OrderDetailID) AS item_count
                FROM tb_orders o
                LEFT JOIN tb_customers c ON o.i_CustomerID = c.i_customerid
                LEFT JOIN tb_employees e ON o.i_EmployeeID = e.i_EmployeeID
                LEFT JOIN tb_shippers s ON o.i_ShipperID = s.i_ShipperID
                LEFT JOIN tb_orderdetails od ON o.i_OrderID = od.i_OrderID
                LEFT JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                {$whereSql}
                GROUP BY o.i_OrderID, o.i_CustomerID, o.i_EmployeeID, o.c_OrderDate, o.i_ShipperID, 
                         c.c_customername, c.c_country, e.c_FirstName, e.c_LastName, s.c_ShipperName
                ORDER BY o.i_OrderID DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll();

        // Round amounts
        foreach ($orders as &$ord) {
            $ord['total_amount'] = round((float)$ord['total_amount'], 2);
        }

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        Response::success('ดึงรายการคำสั่งซื้อสำเร็จ', $orders, [
            'current_page'  => $page,
            'per_page'      => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages
        ]);
    }

    /**
     * Get single order details with item list
     * GET /api/orders/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;

        $sql = "SELECT o.i_OrderID, o.i_CustomerID, o.i_EmployeeID, o.c_OrderDate, o.i_ShipperID,
                       c.c_customername, c.c_contactname, c.c_address, c.c_city, c.c_country, c.c_postalcode,
                       CONCAT(e.c_FirstName, ' ', e.c_LastName) AS employee_name,
                       s.c_ShipperName, s.c_Phone AS shipper_phone
                FROM tb_orders o
                LEFT JOIN tb_customers c ON o.i_CustomerID = c.i_customerid
                LEFT JOIN tb_employees e ON o.i_EmployeeID = e.i_EmployeeID
                LEFT JOIN tb_shippers s ON o.i_ShipperID = s.i_ShipperID
                WHERE o.i_OrderID = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch();

        if (!$order) {
            Response::notFound("ไม่พบคำสั่งซื้อหมายเลข #{$id}");
        }

        // Fetch Order Details (Items)
        $itemSql = "SELECT od.i_OrderDetailID, od.i_OrderID, od.i_ProductID, od.i_Quantity,
                           p.c_ProductName, p.c_Unit, p.i_Price,
                           ROUND(od.i_Quantity * p.i_Price, 2) AS line_total,
                           cat.c_CategoryName
                    FROM tb_orderdetails od
                    INNER JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                    LEFT JOIN tb_categories cat ON p.i_CategoryID = cat.i_CategoryID
                    WHERE od.i_OrderID = :id
                    ORDER BY od.i_OrderDetailID ASC";

        $itemStmt = $this->db->prepare($itemSql);
        $itemStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $itemStmt->execute();
        $items = $itemStmt->fetchAll();

        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += (float)$item['line_total'];
        }

        $order['items'] = $items;
        $order['total_amount'] = round($totalAmount, 2);

        Response::success("ดึงรายละเอียดคำสั่งซื้อ #{$id} สำเร็จ", $order);
    }

    /**
     * Create a new order with optional initial items (Database Transaction)
     * POST /api/orders
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'i_CustomerID' => 'required|integer',
            'i_EmployeeID' => 'required|integer',
            'i_ShipperID'  => 'required|integer',
            'c_OrderDate'  => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('กรุณาระบุข้อมูลคำสั่งซื้อให้ครบถ้วน', 422, $errors);
        }

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO tb_orders (i_CustomerID, i_EmployeeID, c_OrderDate, i_ShipperID)
                    VALUES (:customer_id, :employee_id, :order_date, :shipper_id)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':customer_id', (int)$body['i_CustomerID'], PDO::PARAM_INT);
            $stmt->bindValue(':employee_id', (int)$body['i_EmployeeID'], PDO::PARAM_INT);
            $stmt->bindValue(':order_date', trim($body['c_OrderDate']), PDO::PARAM_STR);
            $stmt->bindValue(':shipper_id', (int)$body['i_ShipperID'], PDO::PARAM_INT);
            $stmt->execute();

            $newOrderId = (int)$this->db->lastInsertId();

            // Insert items if provided
            if (!empty($body['items']) && is_array($body['items'])) {
                $detailSql = "INSERT INTO tb_orderdetails (i_OrderID, i_ProductID, i_Quantity) 
                              VALUES (:order_id, :product_id, :quantity)";
                $detailStmt = $this->db->prepare($detailSql);

                foreach ($body['items'] as $item) {
                    if (!empty($item['i_ProductID']) && !empty($item['i_Quantity'])) {
                        $detailStmt->bindValue(':order_id', $newOrderId, PDO::PARAM_INT);
                        $detailStmt->bindValue(':product_id', (int)$item['i_ProductID'], PDO::PARAM_INT);
                        $detailStmt->bindValue(':quantity', max(1, (int)$item['i_Quantity']), PDO::PARAM_INT);
                        $detailStmt->execute();
                    }
                }
            }

            $this->db->commit();
            $this->show($newOrderId);

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::error('เกิดข้อผิดพลาดในการสร้างคำสั่งซื้อ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update order info
     * PUT /api/orders/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        $check = $this->db->prepare("SELECT i_OrderID FROM tb_orders WHERE i_OrderID = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบคำสั่งซื้อ #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'i_CustomerID' => 'required|integer',
            'i_EmployeeID' => 'required|integer',
            'i_ShipperID'  => 'required|integer',
            'c_OrderDate'  => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "UPDATE tb_orders
                SET i_CustomerID = :customer_id,
                    i_EmployeeID = :employee_id,
                    c_OrderDate  = :order_date,
                    i_ShipperID  = :shipper_id
                WHERE i_OrderID = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':customer_id', (int)$body['i_CustomerID'], PDO::PARAM_INT);
        $stmt->bindValue(':employee_id', (int)$body['i_EmployeeID'], PDO::PARAM_INT);
        $stmt->bindValue(':order_date', trim($body['c_OrderDate']), PDO::PARAM_STR);
        $stmt->bindValue(':shipper_id', (int)$body['i_ShipperID'], PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete order and all its details in a transaction
     * DELETE /api/orders/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        try {
            $this->db->beginTransaction();

            // Delete order details first
            $delItems = $this->db->prepare("DELETE FROM tb_orderdetails WHERE i_OrderID = :id");
            $delItems->bindParam(':id', $id, PDO::PARAM_INT);
            $delItems->execute();

            // Delete order
            $delOrder = $this->db->prepare("DELETE FROM tb_orders WHERE i_OrderID = :id");
            $delOrder->bindParam(':id', $id, PDO::PARAM_INT);
            $delOrder->execute();

            if ($delOrder->rowCount() === 0) {
                $this->db->rollBack();
                Response::notFound("ไม่พบคำสั่งซื้อ #{$id}");
            }

            $this->db->commit();
            Response::success("ลบคำสั่งซื้อ #{$id} และรายการสินค้าที่เกี่ยวข้องเรียบร้อยแล้ว");

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Response::error('เกิดข้อผิดพลาดในการลบคำสั่งซื้อ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add an item to an existing order
     * POST /api/orders/{id}/items
     */
    public function addItem($id): void
    {
        $orderId = (int)$id;
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'i_ProductID' => 'required|integer',
            'i_Quantity'  => 'required|integer|min:1'
        ]);

        if (!empty($errors)) {
            Response::error('กรุณาระบุสินค้าและจำนวนให้ถูกต้อง', 422, $errors);
        }

        $sql = "INSERT INTO tb_orderdetails (i_OrderID, i_ProductID, i_Quantity) 
                VALUES (:order_id, :product_id, :quantity)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', (int)$body['i_ProductID'], PDO::PARAM_INT);
        $stmt->bindValue(':quantity', (int)$body['i_Quantity'], PDO::PARAM_INT);
        $stmt->execute();

        $this->show($orderId);
    }

    /**
     * Delete an item from order details
     * DELETE /api/orders/{id}/items/{detailId}
     */
    public function deleteItem($id, $detailId): void
    {
        $orderId = (int)$id;
        $detailId = (int)$detailId;

        $sql = "DELETE FROM tb_orderdetails WHERE i_OrderDetailID = :detail_id AND i_OrderID = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':detail_id', $detailId, PDO::PARAM_INT);
        $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบรายการสินค้า #{$detailId} ในคำสั่งซื้อ #{$orderId}");
        }

        $this->show($orderId);
    }
}
