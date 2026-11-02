<?php
/**
 * api/controllers/CategoryController.php
 * RESTful CRUD Controller for Categories
 */

require_once __DIR__ . '/../core/BaseController.php';

class CategoryController extends BaseController
{
    /**
     * List all categories with product counts
     * GET /api/categories
     */
    public function index(): void
    {
        $sql = "SELECT c.i_CategoryID, c.c_CategoryName, c.c_Description,
                       COUNT(p.i_ProductID) AS product_count
                FROM tb_categories c
                LEFT JOIN tb_products p ON c.i_CategoryID = p.i_CategoryID
                GROUP BY c.i_CategoryID, c.c_CategoryName, c.c_Description
                ORDER BY c.i_CategoryID ASC";

        $categories = $this->db->query($sql)->fetchAll();
        Response::success('ดึงรายการหมวดหมู่สินค้าสำเร็จ', $categories);
    }

    /**
     * Show single category by ID
     * GET /api/categories/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;
        $sql = "SELECT i_CategoryID, c_CategoryName, c_Description 
                FROM tb_categories 
                WHERE i_CategoryID = :id 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch();

        if (!$category) {
            Response::notFound("ไม่พบหมวดหมู่สินค้าที่รหัส #{$id}");
        }

        // Also fetch products in this category
        $prodSql = "SELECT i_ProductID, c_ProductName, i_Price, c_Unit 
                    FROM tb_products 
                    WHERE i_CategoryID = :id 
                    ORDER BY i_ProductID ASC";
        $prodStmt = $this->db->prepare($prodSql);
        $prodStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $prodStmt->execute();
        $category['products'] = $prodStmt->fetchAll();

        Response::success("ดึงข้อมูลหมวดหมู่สินค้า #{$id} สำเร็จ", $category);
    }

    /**
     * Create a new category
     * POST /api/categories
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_CategoryName' => 'required',
            'c_Description'  => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('กรุณากรอกชื่อหมวดหมู่และคำอธิบาย', 422, $errors);
        }

        $sql = "INSERT INTO tb_categories (c_CategoryName, c_Description) 
                VALUES (:name, :desc)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_CategoryName']), PDO::PARAM_STR);
        $stmt->bindValue(':desc', trim($body['c_Description']), PDO::PARAM_STR);
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId();
        $this->show($newId);
    }

    /**
     * Update an existing category
     * PUT /api/categories/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        $check = $this->db->prepare("SELECT i_CategoryID FROM tb_categories WHERE i_CategoryID = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบหมวดหมู่สินค้า #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_CategoryName' => 'required',
            'c_Description'  => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('กรุณากรอกชื่อหมวดหมู่และคำอธิบาย', 422, $errors);
        }

        $sql = "UPDATE tb_categories 
                SET c_CategoryName = :name, 
                    c_Description  = :desc 
                WHERE i_CategoryID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_CategoryName']), PDO::PARAM_STR);
        $stmt->bindValue(':desc', trim($body['c_Description']), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete category
     * DELETE /api/categories/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        // Check if products exist in category
        $checkProd = $this->db->prepare("SELECT COUNT(*) FROM tb_products WHERE i_CategoryID = :id");
        $checkProd->bindParam(':id', $id, PDO::PARAM_INT);
        $checkProd->execute();
        $prodCount = (int)$checkProd->fetchColumn();

        if ($prodCount > 0) {
            Response::error("ไม่สามารถลบหมวดหมู่นี้ได้เนื่องจากมีสินค้าในหมวดหมู่นี้อยู่ {$prodCount} รายการ", 409);
        }

        $sql = "DELETE FROM tb_categories WHERE i_CategoryID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบหมวดหมู่สินค้า #{$id}");
        }

        Response::success("ลบหมวดหมู่สินค้า #{$id} เรียบร้อยแล้ว");
    }
}
