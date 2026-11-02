<?php
/**
 * api/controllers/SupplierController.php
 * RESTful CRUD Controller for Suppliers (Screen 9)
 */

require_once __DIR__ . '/../core/BaseController.php';

class SupplierController extends BaseController
{
    /**
     * List suppliers with product counts and search
     * GET /api/suppliers
     */
    public function index(): void
    {
        [$page, $limit, $offset] = $this->getPaginationParams(10);
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $whereConditions = [];
        $params = [];

        if ($search !== '') {
            $whereConditions[] = "(c_SupplierName LIKE :search OR c_ContactName LIKE :search OR c_Country LIKE :search OR c_City LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $countSql = "SELECT COUNT(*) FROM tb_suppliers {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalRecords = (int)$countStmt->fetchColumn();

        $sql = "SELECT s.i_SupplierID, s.c_SupplierName, s.c_ContactName, s.c_Address, s.c_City, s.c_PostalCode, s.c_Country, s.c_Phone,
                       COUNT(p.i_ProductID) AS product_count
                FROM tb_suppliers s
                LEFT JOIN tb_products p ON s.i_SupplierID = p.i_SupplierID
                {$whereSql}
                GROUP BY s.i_SupplierID, s.c_SupplierName, s.c_ContactName, s.c_Address, s.c_City, s.c_PostalCode, s.c_Country, s.c_Phone
                ORDER BY s.i_SupplierID ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $suppliers = $stmt->fetchAll();

        $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;

        Response::success('ดึงรายการผู้จัดจำหน่ายสำเร็จ', $suppliers, [
            'current_page'  => $page,
            'per_page'      => $limit,
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages
        ]);
    }

    /**
     * Get single supplier by ID
     * GET /api/suppliers/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;
        $sql = "SELECT * FROM tb_suppliers WHERE i_SupplierID = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $supplier = $stmt->fetch();

        if (!$supplier) {
            Response::notFound("ไม่พบข้อมูลผู้จัดจำหน่าย #{$id}");
        }

        // Fetch products supplied
        $prodSql = "SELECT i_ProductID, c_ProductName, i_Price, c_Unit 
                    FROM tb_products 
                    WHERE i_SupplierID = :id 
                    ORDER BY i_ProductID ASC";
        $prodStmt = $this->db->prepare($prodSql);
        $prodStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $prodStmt->execute();
        $supplier['products'] = $prodStmt->fetchAll();

        Response::success("ดึงข้อมูลผู้จัดจำหน่าย #{$id} สำเร็จ", $supplier);
    }

    /**
     * Create supplier
     * POST /api/suppliers
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_SupplierName' => 'required',
            'c_ContactName'  => 'required',
            'c_Country'      => 'required',
            'c_Phone'        => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "INSERT INTO tb_suppliers (c_SupplierName, c_ContactName, c_Address, c_City, c_PostalCode, c_Country, c_Phone)
                VALUES (:name, :contact, :address, :city, :postal, :country, :phone)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_SupplierName']), PDO::PARAM_STR);
        $stmt->bindValue(':contact', trim($body['c_ContactName']), PDO::PARAM_STR);
        $stmt->bindValue(':address', trim($body['c_Address'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':city', trim($body['c_City'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':postal', trim($body['c_PostalCode'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':country', trim($body['c_Country']), PDO::PARAM_STR);
        $stmt->bindValue(':phone', trim($body['c_Phone']), PDO::PARAM_STR);
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId();
        $this->show($newId);
    }

    /**
     * Update supplier
     * PUT /api/suppliers/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        $check = $this->db->prepare("SELECT i_SupplierID FROM tb_suppliers WHERE i_SupplierID = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบข้อมูลผู้จัดจำหน่าย #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_SupplierName' => 'required',
            'c_ContactName'  => 'required',
            'c_Country'      => 'required',
            'c_Phone'        => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "UPDATE tb_suppliers 
                SET c_SupplierName = :name,
                    c_ContactName  = :contact,
                    c_Address      = :address,
                    c_City         = :city,
                    c_PostalCode   = :postal,
                    c_Country      = :country,
                    c_Phone        = :phone
                WHERE i_SupplierID = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_SupplierName']), PDO::PARAM_STR);
        $stmt->bindValue(':contact', trim($body['c_ContactName']), PDO::PARAM_STR);
        $stmt->bindValue(':address', trim($body['c_Address'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':city', trim($body['c_City'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':postal', trim($body['c_PostalCode'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':country', trim($body['c_Country']), PDO::PARAM_STR);
        $stmt->bindValue(':phone', trim($body['c_Phone']), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete supplier
     * DELETE /api/suppliers/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        $checkProd = $this->db->prepare("SELECT COUNT(*) FROM tb_products WHERE i_SupplierID = :id");
        $checkProd->bindParam(':id', $id, PDO::PARAM_INT);
        $checkProd->execute();
        $prodCount = (int)$checkProd->fetchColumn();

        if ($prodCount > 0) {
            Response::error("ไม่สามารถลบผู้จัดจำหน่ายรายนี้ได้ เนื่องจากมีสินค้าที่สั่งจากผู้จัดจำหน่ายนี้ {$prodCount} รายการ", 409);
        }

        $sql = "DELETE FROM tb_suppliers WHERE i_SupplierID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบข้อมูลผู้จัดจำหน่าย #{$id}");
        }

        Response::success("ลบข้อมูลผู้จัดจำหน่าย #{$id} เรียบร้อยแล้ว");
    }
}
