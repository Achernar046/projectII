<?php
/**
 * api/controllers/AuthController.php
 * Authentication Controller handling Login, JWT Issuing, and Profile Info
 */

require_once __DIR__ . '/../core/BaseController.php';

class AuthController extends BaseController
{
    /**
     * User Login and JWT Token Generation
     * POST /api/auth/login
     */
    public function login(): void
    {
        $body = $this->getJsonBody();

        $errors = $this->validate($body, [
            'username' => 'required',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            Response::error('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 422, $errors);
        }

        $username = trim($body['username']);
        $password = (string)$body['password'];

        // Prepared Statement to query user by username or email
        $sql = "SELECT i_user_id, c_user_name, email, password_hash, c_user_pwd, full_name, role 
                FROM tb_users 
                WHERE c_user_name = :user OR email = :email 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':user', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $username, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();

        if (!$user) {
            Response::unauthorized('ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
        }

        // Verify password: check password_hash (bcrypt) first, or fallback to plain text c_user_pwd
        $isPasswordValid = false;
        if (!empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
            $isPasswordValid = true;
        } elseif (!empty($user['c_user_pwd']) && $user['c_user_pwd'] === $password) {
            $isPasswordValid = true;
        }

        if (!$isPasswordValid) {
            Response::unauthorized('ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
        }

        // Generate JWT Token
        $tokenPayload = [
            'user_id'   => (int)$user['i_user_id'],
            'username'  => $user['c_user_name'] ?? '',
            'email'     => $user['email'] ?? '',
            'full_name' => $user['full_name'] ?? $user['c_user_name'] ?? 'ผู้ใช้งาน',
            'role'      => $user['role'] ?? 'admin'
        ];

        $token = JWT::encode($tokenPayload);

        $config = require __DIR__ . '/../config/jwt.php';

        Response::success('เข้าสู่ระบบสำเร็จ', [
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int)$config['expiry'],
            'user'       => $tokenPayload
        ]);
    }

    /**
     * Get Current Authenticated User Information
     * GET /api/auth/me
     */
    public function me(): void
    {
        $payload = AuthMiddleware::user();
        if (!$payload) {
            $payload = AuthMiddleware::authenticate();
        }

        Response::success('ดึงข้อมูลโปรไฟล์ผู้ใช้สำเร็จ', [
            'user' => $payload
        ]);
    }

    /**
     * Logout
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        // On JWT architecture, client discards token
        Response::success('ออกจากระบบเรียบร้อยแล้ว');
    }
}
