<?php
/**
 * api/controllers/ProductController.php
 * RESTful CRUD Controller for Products
 */

require_once __DIR__ . '/../core/BaseController.php';

class ProductController extends BaseController
{
    /**
     * List products with search, category/supplier filtering, sorting, and pagination
     * GET /api/products
     */
    public function index(): void
    {
        [$page, $limit, $offset] = $this->getPaginationParams(10);

        $search     = isset($_GET['search']) ? trim($_GET['search']) : '';
        $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $supplierId = isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
        $allowedSorts = ['i_ProductID' => 'p.i_ProductID', 'c_ProductName' => 'p.c_ProductName', 'i_Price' => 'p.i_Price'];
        $sortParam   = $_GET['sort'] ?? 'i_ProductID';
        $sort        = $allowedSorts[$sortParam] ?? 'p.i_ProductID';
        $direction   = strtoupper($_GET['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $whereConditions = [];
        $params = [];

        if ($search !== '') {
            $whereConditions[] = "(p.c_ProductName LIKE :search OR p.c_Unit LIKE :search OR p.i_ProductID = :search_id)";
            $params[':search'] = "%{$search}%";
            $params[':search_id'] = is_numeric($search) ? (int)$search : 0;
        }

        if ($categoryId !== null) {
            $whereConditions[] = "p.i_CategoryID = :cat_id";
            $params[':cat_id'] = $categoryId;
        }

        if ($supplierId !== null) {
            $whereConditions[] = "p.i_SupplierID = :sup_id";
            $params[':sup_id'] = $supplierId;
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Count Total Records (Prepared Statement)
        $countSql = "SELECT COUNT(*) FROM tb_products p {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        // Query Records (Prepared Statement with JOIN)
        $sql = "SELECT p.i_ProductID, p.c_ProductName, p.i_SupplierID, p.i_CategoryID, p.c_Unit, p.i_Price,
                       c.c_CategoryName, s.c_SupplierName
                FROM tb_products p
                LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                LEFT JOIN tb_suppliers s ON p.i_SupplierID = s.i_SupplierID
                {$whereSql}
                ORDER BY {$sort} {$direction}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll();

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        Response::success('ดึงรายการสินค้าสำเร็จ', $products, [
            'current_page'  => $page,
            'per_page'      => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages
        ]);
    }

    /**
     * Get single product by ID
     * GET /api/products/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;
        $sql = "SELECT p.i_ProductID, p.c_ProductName, p.i_SupplierID, p.i_CategoryID, p.c_Unit, p.i_Price,
                       c.c_CategoryName, s.c_SupplierName
                FROM tb_products p
                LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                LEFT JOIN tb_suppliers s ON p.i_SupplierID = s.i_SupplierID
                WHERE p.i_ProductID = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch();

        if (!$product) {
            Response::notFound("ไม่พบสินค้าที่รหัส #{$id}");
        }

        Response::success("ดึงข้อมูลสินค้า #{$id} สำเร็จ", $product);
    }

    /**
     * Create a new product
     * POST /api/products
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_ProductName' => 'required',
            'i_SupplierID'  => 'required|integer',
            'i_CategoryID'  => 'required|integer',
            'c_Unit'        => 'required',
            'i_Price'       => 'required|numeric|min:0'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "INSERT INTO tb_products (c_ProductName, i_SupplierID, i_CategoryID, c_Unit, i_Price)
                VALUES (:name, :supplier_id, :category_id, :unit, :price)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_ProductName']), PDO::PARAM_STR);
        $stmt->bindValue(':supplier_id', (int)$body['i_SupplierID'], PDO::PARAM_INT);
        $stmt->bindValue(':category_id', (int)$body['i_CategoryID'], PDO::PARAM_INT);
        $stmt->bindValue(':unit', trim($body['c_Unit']), PDO::PARAM_STR);
        $stmt->bindValue(':price', (float)$body['i_Price']);
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId();

        $this->show($newId);
    }

    /**
     * Update an existing product
     * PUT /api/products/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        // Check existence
        $check = $this->db->prepare("SELECT i_ProductID FROM tb_products WHERE i_ProductID = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบสินค้าที่ต้องการแก้ไข #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_ProductName' => 'required',
            'i_SupplierID'  => 'required|integer',
            'i_CategoryID'  => 'required|integer',
            'c_Unit'        => 'required',
            'i_Price'       => 'required|numeric|min:0'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "UPDATE tb_products 
                SET c_ProductName = :name, 
                    i_SupplierID  = :supplier_id, 
                    i_CategoryID  = :category_id, 
                    c_Unit        = :unit, 
                    i_Price       = :price
                WHERE i_ProductID = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_ProductName']), PDO::PARAM_STR);
        $stmt->bindValue(':supplier_id', (int)$body['i_SupplierID'], PDO::PARAM_INT);
        $stmt->bindValue(':category_id', (int)$body['i_CategoryID'], PDO::PARAM_INT);
        $stmt->bindValue(':unit', trim($body['c_Unit']), PDO::PARAM_STR);
        $stmt->bindValue(':price', (float)$body['i_Price']);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete product
     * DELETE /api/products/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        // Check if product is referenced in order details
        $checkOrder = $this->db->prepare("SELECT COUNT(*) FROM tb_orderdetails WHERE i_ProductID = :id");
        $checkOrder->bindParam(':id', $id, PDO::PARAM_INT);
        $checkOrder->execute();
        $orderCount = (int)$checkOrder->fetchColumn();

        if ($orderCount > 0) {
            Response::error("ไม่สามารถลบสินค้านี้ได้ เนื่องจากมีประวัติอยู่ในคำสั่งซื้อจำนวน {$orderCount} รายการ", 409);
        }

        $sql = "DELETE FROM tb_products WHERE i_ProductID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบสินค้าที่ต้องการลบ #{$id}");
        }

        Response::success("ลบสินค้า #{$id} เรียบร้อยแล้ว");
    }
}
