<?php
/**
 * api/controllers/DashboardController.php
 * Dashboard Statistics, KPIs, and Chart Aggregations
 */

require_once __DIR__ . '/../core/BaseController.php';

class DashboardController extends BaseController
{
    /**
     * Get KPI Summary Cards
     * GET /api/dashboard/kpis
     */
    public function kpis(): void
    {
        // 1. Total Revenue: SUM(quantity * price)
        $revenueSql = "SELECT COALESCE(SUM(od.i_Quantity * p.i_Price), 0) AS total_revenue
                       FROM tb_orderdetails od
                       INNER JOIN tb_products p ON od.i_ProductID = p.i_ProductID";
        $totalRevenue = (float)$this->db->query($revenueSql)->fetchColumn();

        // 2. Total Orders
        $totalOrders = (int)$this->db->query("SELECT COUNT(*) FROM tb_orders")->fetchColumn();

        // 3. Total Products
        $totalProducts = (int)$this->db->query("SELECT COUNT(*) FROM tb_products")->fetchColumn();

        // 4. Total Customers
        $totalCustomers = (int)$this->db->query("SELECT COUNT(*) FROM tb_customers")->fetchColumn();

        // 5. Total Categories
        $totalCategories = (int)$this->db->query("SELECT COUNT(*) FROM tb_categories")->fetchColumn();

        // 6. Average Order Value (AOV)
        $avgOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

        Response::success('ดึงข้อมูล KPI แดชบอร์ดสำเร็จ', [
            'total_revenue'    => round($totalRevenue, 2),
            'total_orders'     => $totalOrders,
            'total_products'   => $totalProducts,
            'total_customers'  => $totalCustomers,
            'total_categories' => $totalCategories,
            'avg_order_value'  => round($avgOrderValue, 2),
        ]);
    }

    /**
     * Get Chart & Analytics Data
     * GET /api/dashboard/charts
     */
    public function charts(): void
    {
        // 1. Top 5 Best Selling Products (Quantity sold)
        $topProductsSql = "SELECT p.i_ProductID, p.c_ProductName, c.c_CategoryName, 
                                  SUM(od.i_Quantity) AS total_sold,
                                  ROUND(SUM(od.i_Quantity * p.i_Price), 2) AS total_sales
                           FROM tb_orderdetails od
                           INNER JOIN tb_products p ON od.i_ProductID = p.i_ProductID
                           LEFT JOIN tb_categories c ON p.i_CategoryID = c.i_CategoryID
                           GROUP BY p.i_ProductID, p.c_ProductName, c.c_CategoryName
                           ORDER BY total_sold DESC
                           LIMIT 5";
        $topProducts = $this->db->query($topProductsSql)->fetchAll();

        // 2. Sales by Category
        $catSalesSql = "SELECT c.i_CategoryID, c.c_CategoryName,
                               COUNT(DISTINCT p.i_ProductID) AS product_count,
                               COALESCE(SUM(od.i_Quantity * p.i_Price), 0) AS total_revenue
                        FROM tb_categories c
                        LEFT JOIN tb_products p ON c.i_CategoryID = p.i_CategoryID
                        LEFT JOIN tb_orderdetails od ON p.i_ProductID = od.i_ProductID
                        GROUP BY c.i_CategoryID, c.c_CategoryName
                        ORDER BY total_revenue DESC";
        $categorySales = $this->db->query($catSalesSql)->fetchAll();

        // 3. Top 5 Customers by Order Count
        $topCustSql = "SELECT c.i_customerid, c.c_customername, c.c_country,
                              COUNT(o.i_OrderID) AS order_count
                       FROM tb_customers c
                       INNER JOIN tb_orders o ON c.i_customerid = o.i_CustomerID
                       GROUP BY c.i_customerid, c.c_customername, c.c_country
                       ORDER BY order_count DESC
                       LIMIT 5";
        $topCustomers = $this->db->query($topCustSql)->fetchAll();

        // 4. Monthly Orders Distribution (Simulated/Aggregated from c_OrderDate)
        // c_OrderDate has formats like d/m/Y or Y-m-d
        $monthlySql = "SELECT o.c_OrderDate, od.i_Quantity * p.i_Price as item_total
                       FROM tb_orders o
                       JOIN tb_orderdetails od ON o.i_OrderID = od.i_OrderID
                       JOIN tb_products p ON od.i_ProductID = p.i_ProductID";
        $ordersRaw = $this->db->query($monthlySql)->fetchAll();

        $monthlyTotals = [];
        $monthNames = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyTotals[$m] = 0;
        }

        foreach ($ordersRaw as $row) {
            $dateStr = $row['c_OrderDate'] ?? '';
            $monthNum = 0;
            if (preg_match('#/(\d{1,2})/#', $dateStr, $matches)) {
                $monthNum = (int)$matches[1];
            } elseif (preg_match('#-(\d{2})-#', $dateStr, $matches)) {
                $monthNum = (int)$matches[1];
            }
            if ($monthNum >= 1 && $monthNum <= 12) {
                $monthlyTotals[$monthNum] += (float)$row['item_total'];
            }
        }

        $monthlyChartData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyChartData[] = [
                'month_num'  => $m,
                'month_name' => $monthNames[$m - 1],
                'revenue'    => round($monthlyTotals[$m], 2)
            ];
        }

        Response::success('ดึงข้อมูลชาร์ตและสถิติสำเร็จ', [
            'top_products'   => $topProducts,
            'category_sales' => $categorySales,
            'top_customers'  => $topCustomers,
            'monthly_trend'  => $monthlyChartData
        ]);
    }
}
