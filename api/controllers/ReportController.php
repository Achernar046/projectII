<?php
/**
 * api/controllers/ReportController.php
 * Reports & Data Export Controller (Screen 12)
 */

require_once __DIR__ . '/../core/BaseController.php';

class ReportController extends BaseController
{
    /**
     * Get Sales Performance Summary
     * GET /api/reports/sales
     */
    public function salesSummary(): void
    {
        // Category performance
        $catSql = "SELECT c.c_CategoryName,
                          COUNT(DISTINCT p.i_ProductID) AS product_count,
                          COALESCE(SUM(od.i_Quantity), 0) AS total_units_sold,
                          ROUND(COALESCE(SUM(od.i_Quantity * p.i_Price), 0), 2) AS total_sales
                   FROM tb_categories c
                   LEFT JOIN tb_products p ON c.i_CategoryID = p.i_CategoryID
                   LEFT JOIN tb_orderdetails od ON p.i_ProductID = od.i_ProductID
                   GROUP BY c.i_CategoryID, c.c_CategoryName
                   ORDER BY total_sales DESC";
        $categoryReport = $this->db->query($catSql)->fetchAll();

        // Shipper performance
        $shipSql = "SELECT s.c_ShipperName,
                           COUNT(DISTINCT o.i_OrderID) AS total_orders,
                           ROUND(COALESCE(SUM(od.i_Quantity * p.i_Price), 0), 2) AS total_revenue
                    FROM tb_shippers s
                    LEFT JOIN tb_orders o ON s.i_ShipperID = o.i_ShipperID
                    LEFT JOIN tb_orderdetails od ON o.i_OrderID = od.i_OrderID
                    LEFT JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                    GROUP BY s.i_ShipperID, s.c_ShipperName
                    ORDER BY total_orders DESC";
        $shipperReport = $this->db->query($shipSql)->fetchAll();

        // Employee sales ranking
        $empSql = "SELECT CONCAT(e.c_FirstName, ' ', e.c_LastName) AS employee_name,
                          COUNT(DISTINCT o.i_OrderID) AS orders_handled,
                          ROUND(COALESCE(SUM(od.i_Quantity * p.i_Price), 0), 2) AS total_sales
                   FROM tb_employees e
                   LEFT JOIN tb_orders o ON e.i_EmployeeID = o.i_EmployeeID
                   LEFT JOIN tb_orderdetails od ON o.i_OrderID = od.i_OrderID
                   LEFT JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                   GROUP BY e.i_EmployeeID, e.c_FirstName, e.c_LastName
                   ORDER BY total_sales DESC";
        $employeeReport = $this->db->query($empSql)->fetchAll();

        Response::success('ดึงรายงานสรุปยอดขายสำเร็จ', [
            'by_category' => $categoryReport,
            'by_shipper'  => $shipperReport,
            'by_employee' => $employeeReport,
        ]);
    }

    /**
     * Export data to CSV format
     * GET /api/reports/export?type=products|orders|categories
     */
    public function export(): void
    {
        $type = $_GET['type'] ?? 'products';
        $filename = "report_{$type}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Output UTF-8 BOM so Thai characters display correctly in Microsoft Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if ($type === 'products') {
            fputcsv($output, ['รหัสสินค้า', 'ชื่อสินค้า', 'หมวดหมู่', 'ผู้จัดจำหน่าย', 'หน่วย', 'ราคา (บาท)']);
            $sql = "SELECT p.i_ProductID, p.c_ProductName, c.c_CategoryName, s.c_SupplierName, p.c_Unit, p.i_Price
                    FROM tb_products p
                    LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                    LEFT JOIN tb_suppliers s ON p.i_SupplierID = s.i_SupplierID
                    ORDER BY p.i_ProductID ASC";
            $stmt = $this->db->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                fputcsv($output, $row);
            }
        } elseif ($type === 'orders') {
            fputcsv($output, ['รหัสคำสั่งซื้อ', 'วันที่สั่งซื้อ', 'ลูกค้า', 'ประเทศ', 'พนักงานขาย', 'บริษัทขนส่ง', 'ยอดรวม (บาท)']);
            $sql = "SELECT o.i_OrderID, o.c_OrderDate, c.c_customername, c.c_country,
                           CONCAT(e.c_FirstName, ' ', e.c_LastName) AS emp,
                           s.c_ShipperName,
                           COALESCE(SUM(od.i_Quantity * p.i_Price), 0) AS total
                    FROM tb_orders o
                    LEFT JOIN tb_customers c ON o.i_CustomerID = c.i_customerid
                    LEFT JOIN tb_employees e ON o.i_EmployeeID = e.i_EmployeeID
                    LEFT JOIN tb_shippers s ON o.i_ShipperID = s.i_ShipperID
                    LEFT JOIN tb_orderdetails od ON o.i_OrderID = od.i_OrderID
                    LEFT JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                    GROUP BY o.i_OrderID, o.c_OrderDate, c.c_customername, c.c_country, emp, s.c_ShipperName
                    ORDER BY o.i_OrderID DESC";
            $stmt = $this->db->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $row[6] = round((float)$row[6], 2);
                fputcsv($output, $row);
            }
        } else {
            fputcsv($output, ['รหัสหมวดหมู่', 'ชื่อหมวดหมู่', 'คำอธิบาย']);
            $sql = "SELECT i_CategoryID, c_CategoryName, c_Description FROM tb_categories ORDER BY i_CategoryID ASC";
            $stmt = $this->db->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }
}
