<?php
/**
 * api/controllers/ShipperController.php
 * RESTful CRUD Controller for Shippers (Screen 11)
 */

require_once __DIR__ . '/../core/BaseController.php';

class ShipperController extends BaseController
{
    /**
     * List all shippers with order count
     * GET /api/shippers
     */
    public function index(): void
    {
        $sql = "SELECT s.i_ShipperID, s.c_ShipperName, s.c_Phone,
                       COUNT(o.i_OrderID) AS total_shipments
                FROM tb_shippers s
                LEFT JOIN tb_orders o ON s.i_ShipperID = o.i_ShipperID
                GROUP BY s.i_ShipperID, s.c_ShipperName, s.c_Phone
                ORDER BY s.i_ShipperID ASC";

        $shippers = $this->db->query($sql)->fetchAll();
        Response::success('ดึงรายการบริษัทขนส่งสำเร็จ', $shippers);
    }

    /**
     * Get single shipper by ID
     * GET /api/shippers/{id}
     */
    public function show($id): void
    {
        $id = (int)$id;
        $sql = "SELECT s.i_ShipperID, s.c_ShipperName, s.c_Phone,
                       COUNT(o.i_OrderID) AS total_shipments
                FROM tb_shippers s
                LEFT JOIN tb_orders o ON s.i_ShipperID = o.i_ShipperID
                WHERE s.i_ShipperID = :id
                GROUP BY s.i_ShipperID, s.c_ShipperName, s.c_Phone
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $shipper = $stmt->fetch();

        if (!$shipper) {
            Response::notFound("ไม่พบข้อมูลบริษัทขนส่ง #{$id}");
        }

        Response::success("ดึงข้อมูลบริษัทขนส่ง #{$id} สำเร็จ", $shipper);
    }

    /**
     * Create shipper
     * POST /api/shippers
     */
    public function store(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_ShipperName' => 'required',
            'c_Phone'       => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "INSERT INTO tb_shippers (c_ShipperName, c_Phone)
                VALUES (:name, :phone)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_ShipperName']), PDO::PARAM_STR);
        $stmt->bindValue(':phone', trim($body['c_Phone']), PDO::PARAM_STR);
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId();
        $this->show($newId);
    }

    /**
     * Update shipper
     * PUT /api/shippers/{id}
     */
    public function update($id): void
    {
        $id = (int)$id;

        $check = $this->db->prepare("SELECT i_ShipperID FROM tb_shippers WHERE i_ShipperID = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        if (!$check->fetch()) {
            Response::notFound("ไม่พบข้อมูลบริษัทขนส่ง #{$id}");
        }

        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'c_ShipperName' => 'required',
            'c_Phone'       => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('ข้อมูลไม่ครบถ้วนหรือไม่ถูกต้องตามรูปแบบ', 422, $errors);
        }

        $sql = "UPDATE tb_shippers 
                SET c_ShipperName = :name, 
                    c_Phone       = :phone 
                WHERE i_ShipperID = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', trim($body['c_ShipperName']), PDO::PARAM_STR);
        $stmt->bindValue(':phone', trim($body['c_Phone']), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $this->show($id);
    }

    /**
     * Delete shipper
     * DELETE /api/shippers/{id}
     */
    public function delete($id): void
    {
        $id = (int)$id;

        $checkOrders = $this->db->prepare("SELECT COUNT(*) FROM tb_orders WHERE i_ShipperID = :id");
        $checkOrders->bindParam(':id', $id, PDO::PARAM_INT);
        $checkOrders->execute();
        $orderCount = (int)$checkOrders->fetchColumn();

        if ($orderCount > 0) {
            Response::error("ไม่สามารถลบบริษัทขนส่งนี้ได้เนื่องจากมีคำสั่งซื้อที่ใช้บริการนี้ {$orderCount} รายการ", 409);
        }

        $sql = "DELETE FROM tb_shippers WHERE i_ShipperID = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            Response::notFound("ไม่พบข้อมูลบริษัทขนส่ง #{$id}");
        }

        Response::success("ลบข้อมูลบริษัทขนส่ง #{$id} เรียบร้อยแล้ว");
    }
}
