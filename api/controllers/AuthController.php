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

        if (!is_string($body['username']) || !is_string($body['password'])
            || strlen($body['username']) > 254 || strlen($body['password']) > 1024) {
            Response::error('รูปแบบชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 422);
        }

        $username = trim($body['username']);
        $password = (string)$body['password'];
        if ($username === '') {
            Response::error('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน', 422);
        }
        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipRateKey = 'ip:' . $clientIp;
        $identityRateKey = 'identity:' . $clientIp . ':' . strtolower($username);

        if (LoginRateLimiter::isLimited($ipRateKey, 30) || LoginRateLimiter::isLimited($identityRateKey, 8)) {
            header('Retry-After: 900');
            Response::error('พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่', 429);
        }

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
            LoginRateLimiter::recordFailure($ipRateKey);
            LoginRateLimiter::recordFailure($identityRateKey);
            Response::unauthorized('ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
        }

        // Verify hashed passwords first; upgrade a legacy plaintext password after successful login.
        $isPasswordValid = false;
        $storedHash = (string)($user['password_hash'] ?? '');
        $effectivePasswordHash = $storedHash;
        $legacyPassword = (string)($user['c_user_pwd'] ?? '');
        $legacyPasswordMatched = false;

        if ($storedHash !== '' && password_verify($password, $storedHash)) {
            $isPasswordValid = true;
        } elseif ($legacyPassword !== '' && hash_equals($legacyPassword, $password)) {
            $isPasswordValid = true;
            $legacyPasswordMatched = true;
        }

        if (!$isPasswordValid) {
            LoginRateLimiter::recordFailure($ipRateKey);
            LoginRateLimiter::recordFailure($identityRateKey);
            Response::unauthorized('ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง');
        }

        if ($legacyPasswordMatched || $legacyPassword !== ''
            || ($storedHash !== '' && password_needs_rehash($storedHash, PASSWORD_DEFAULT))) {
            $effectivePasswordHash = password_hash($password, PASSWORD_DEFAULT);
            $upgrade = $this->db->prepare(
                "UPDATE tb_users SET password_hash = :password_hash, c_user_pwd = '' WHERE i_user_id = :user_id"
            );
            $upgrade->bindValue(':password_hash', $effectivePasswordHash, PDO::PARAM_STR);
            $upgrade->bindValue(':user_id', (int)$user['i_user_id'], PDO::PARAM_INT);
            $upgrade->execute();
        }

        LoginRateLimiter::clear($ipRateKey);
        LoginRateLimiter::clear($identityRateKey);

        // Generate JWT Token
        $tokenPayload = [
            'user_id'   => (int)$user['i_user_id'],
            'username'  => $user['c_user_name'] ?? '',
            'email'     => $user['email'] ?? '',
            'full_name' => $user['full_name'] ?? $user['c_user_name'] ?? 'ผู้ใช้งาน',
            'role'      => $user['role'] ?? 'admin'
        ];

        $tokenClaims = $tokenPayload;
        $tokenClaims['auth_version'] = substr(hash('sha256', $effectivePasswordHash), 0, 32);
        $token = JWT::encode($tokenClaims);

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

    /** Change the authenticated user's password. */
    public function changePassword(): void
    {
        $body = $this->getJsonBody();
        $errors = $this->validate($body, [
            'current_password' => 'required',
            'new_password' => 'required',
        ]);
        if (!empty($errors)) {
            Response::error('กรุณากรอกรหัสผ่านปัจจุบันและรหัสผ่านใหม่', 422, $errors);
        }

        if (!is_string($body['current_password']) || !is_string($body['new_password'])
            || strlen($body['current_password']) > 1024 || strlen($body['new_password']) > 72) {
            Response::error('รูปแบบรหัสผ่านไม่ถูกต้อง', 422);
        }

        $newPassword = (string)$body['new_password'];
        if (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
            Response::error('รหัสผ่านใหม่ต้องมีความยาว 12 ถึง 72 ไบต์', 422);
        }

        $user = AuthMiddleware::user();
        $userId = (int)($user['user_id'] ?? 0);
        if ($userId <= 0) {
            Response::unauthorized();
        }

        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipRateKey = 'password-ip:' . $clientIp;
        $identityRateKey = 'password-user:' . $userId;
        if (LoginRateLimiter::isLimited($ipRateKey, 30) || LoginRateLimiter::isLimited($identityRateKey, 8)) {
            header('Retry-After: 900');
            Response::error('พยายามเปลี่ยนรหัสผ่านหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่', 429);
        }

        $stmt = $this->db->prepare('SELECT password_hash, c_user_pwd FROM tb_users WHERE i_user_id = :user_id LIMIT 1');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $credentials = $stmt->fetch();
        $currentPassword = (string)$body['current_password'];
        $storedHash = (string)($credentials['password_hash'] ?? '');
        $legacyPassword = (string)($credentials['c_user_pwd'] ?? '');
        $currentPasswordValid = ($storedHash !== '' && password_verify($currentPassword, $storedHash))
            || ($legacyPassword !== '' && hash_equals($legacyPassword, $currentPassword));

        if (!$currentPasswordValid) {
            LoginRateLimiter::recordFailure($ipRateKey);
            LoginRateLimiter::recordFailure($identityRateKey);
            Response::error('รหัสผ่านปัจจุบันไม่ถูกต้อง', 422);
        }

        if (($storedHash !== '' && password_verify($newPassword, $storedHash))
            || ($legacyPassword !== '' && hash_equals($legacyPassword, $newPassword))) {
            Response::error('กรุณาเลือกรหัสผ่านใหม่ที่ไม่ซ้ำกับรหัสผ่านปัจจุบัน', 422);
        }

        $update = $this->db->prepare(
            "UPDATE tb_users SET password_hash = :password_hash, c_user_pwd = '' WHERE i_user_id = :user_id"
        );
        $update->bindValue(':password_hash', password_hash($newPassword, PASSWORD_DEFAULT), PDO::PARAM_STR);
        $update->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $update->execute();
        LoginRateLimiter::clear($ipRateKey);
        LoginRateLimiter::clear($identityRateKey);

        $token = AuthMiddleware::getBearerToken();
        $currentUser = AuthMiddleware::user();
        if ($token && $currentUser) {
            TokenRevocationStore::revoke($token, (int)($currentUser['exp'] ?? time()));
        }

        Response::success('เปลี่ยนรหัสผ่านสำเร็จ');
    }

    /**
     * Logout
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        $token = AuthMiddleware::getBearerToken();
        $user = AuthMiddleware::user();
        if ($token && $user) {
            TokenRevocationStore::revoke($token, (int)($user['exp'] ?? time()));
        }
        Response::success('ออกจากระบบเรียบร้อยแล้ว');
    }
}
