<?php

namespace Modules\Auth\Services;

use InvalidArgumentException;
use Modules\Auditoria\Services\AuditService;
use Modules\Auth\Repositories\AuthRepository;
use Modules\Identity\Services\CedulaValidationService;
use Modules\Identity\Services\IdScanService;
use Modules\Identity\Services\IdScanStorage;

final class AuthService
{
    private const MIN_PASSWORD_LENGTH = 6;

    /** Intentos fallidos consecutivos que bloquean temporalmente el acceso. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /** Minutos que dura el bloqueo y en los que se cuentan los fallos. */
    private const LOGIN_LOCK_MINUTES = 15;

    public function __construct(
        private AuthRepository $users,
        private AuditService $audit,
        private CedulaValidationService $cedulas,
        private IdScanService $idScans,
        private IdScanStorage $idScanStorage
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->users->findByEmail($normalizedEmail);

        // CA-HU-003-03 / SEC-005: tras 5 fallos consecutivos la cuenta queda
        // bloqueada temporalmente, incluso si después se envía la contraseña
        // correcta. El aviso al usuario sigue siendo genérico.
        if ($this->isLoginLocked($normalizedEmail, $user ? (int) $user['id'] : null)) {
            $this->audit->log(
                'login_failed',
                'Auth',
                'Inicio de sesión bloqueado temporalmente por intentos fallidos.',
                'failed',
                null,
                ['email' => $normalizedEmail, 'locked' => true]
            );

            return false;
        }

        if (!$user || !password_verify($password, $user['password'])) {
            $this->audit->log(
                'login_failed',
                'Auth',
                'Intento de inicio de sesión rechazado.',
                'failed',
                null,
                ['email' => $normalizedEmail]
            );

            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'email' => $user['email'],
            'cedula' => $user['cedula'] ?? null,
            'company_id' => $user['company_id']
                ? (int) $user['company_id']
                : null,
        ];

        $this->audit->log(
            'login',
            'Auth',
            'Inicio de sesión exitoso.',
            'success',
            (int) $user['id'],
            ['role' => $user['role']]
        );

        return true;
    }

    /**
     * Indica si la cuenta debe esperar antes de volver a intentar el acceso.
     *
     * Los fallos se cuentan solo desde el último login correcto y dentro de la
     * ventana de bloqueo; así el contador se reinicia al entrar con éxito y el
     * bloqueo es temporal.
     */
    private function isLoginLocked(string $email, ?int $userId): bool
    {
        $since = $userId !== null ? $this->users->lastSuccessfulLoginAt($userId) : null;
        $failedAttempts = $this->users->countRecentFailedLogins(
            $email,
            $since,
            self::LOGIN_LOCK_MINUTES
        );

        return $failedAttempts >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Registra un candidato validando el reverso de su cédula o tarjeta de identidad.
     *
     * @param array $scan Datos leídos del documento: ['data' => json, 'file' => $_FILES[...]]
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $cedula,
        string $birthDate,
        array $scan = []
    ): bool {
        $normalizedName = trim($name);
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedName === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.'
            );
        }

        $this->assertPasswordConfirmation($password, $passwordConfirm);

        if ($this->users->findByEmail($normalizedEmail)) {
            throw new InvalidArgumentException(
                'El correo electrónico ya está registrado.'
            );
        }

        $document = $this->cedulas->assertFormat($cedula);

        if ($this->users->findByCedulaAndRole($document, 'candidate')) {
            throw new InvalidArgumentException(
                'Esta cédula ya tiene una cuenta de candidato registrada.'
            );
        }

        $idScan = $this->validateIdScan($scan, $normalizedName, $document, $birthDate);

        try {
            $userId = $this->users->createCandidate(
                $normalizedName,
                $normalizedEmail,
                password_hash($password, PASSWORD_DEFAULT),
                $document,
                $idScan
            );
        } catch (\Throwable $exception) {
            // Si la cuenta no se pudo crear, la imagen del documento no debe
            // quedar almacenada sin ningún usuario asociado.
            $this->idScanStorage->remove($idScan['path'] ?? null);

            throw $exception;
        }

        $this->audit->log(
            'register',
            'Auth',
            'Registro de candidato creado.',
            'success',
            $userId,
            ['email' => $normalizedEmail, 'cedula' => $this->mask($document)]
        );

        return true;
    }

    public function logout(?array $user): void
    {
        if ($user) {
            $this->audit->log(
                'logout',
                'Auth',
                'Cierre de sesión.',
                'success',
                (int) $user['id']
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parameters['path'],
                $parameters['domain'],
                $parameters['secure'],
                $parameters['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Registra una empresa y su cuenta de reclutador.
     *
     * Las cuentas de empresa no tienen ningún requisito de edad y su documento
     * se registra solo como dato de la organización (sin consulta al registro civil).
     */
    public function registerCompany(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $companyName,
        string $city,
        string $cedula
    ): bool {
        $normalizedName = trim($name);
        $normalizedEmail = strtolower(trim($email));
        $normalizedCompanyName = trim($companyName);
        $normalizedCity = trim($city);

        if ($normalizedName === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }

        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.'
            );
        }

        $this->assertPasswordConfirmation($password, $passwordConfirm);

        if ($normalizedCompanyName === '') {
            throw new InvalidArgumentException('El nombre de la empresa es obligatorio.');
        }

        if ($normalizedCity === '') {
            throw new InvalidArgumentException('La ciudad es obligatoria.');
        }

        if ($this->users->findByEmail($normalizedEmail)) {
            throw new InvalidArgumentException(
                'El correo electrónico ya está registrado.'
            );
        }

        $document = $this->cedulas->assertFormat($cedula);

        if ($this->users->findByCedulaAndRole($document, 'recruiter')) {
            throw new InvalidArgumentException(
                'Esta cédula ya tiene una cuenta de empresa registrada.'
            );
        }

        $companyId = $this->users->createCompany($normalizedCompanyName, $normalizedCity);

        $userId = $this->users->createRecruiter(
            $normalizedName,
            $normalizedEmail,
            password_hash($password, PASSWORD_DEFAULT),
            $companyId,
            $document
        );

        $this->audit->log(
            'register_company',
            'Auth',
            'Registro de empresa y reclutador creado.',
            'success',
            $userId,
            [
                'email' => $normalizedEmail,
                'company_id' => $companyId,
                'cedula' => $this->mask($document),
            ]
        );

        return true;
    }

    /**
     * Cambia la contraseña de una cuenta verificando la contraseña actual.
     */
    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $passwordConfirm
    ): void {
        $account = $this->users->findById($userId);

        if (!$account) {
            throw new InvalidArgumentException('No se encontró la cuenta asociada a la sesión.');
        }

        if (!password_verify($currentPassword, $account['password'])) {
            $this->audit->log(
                'password_change_failed',
                'Auth',
                'Cambio de contraseña rechazado: la contraseña actual no coincide.',
                'failed',
                $userId,
                ['role' => $account['role']]
            );

            throw new InvalidArgumentException('La contraseña actual no es correcta.');
        }

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'La nueva contraseña debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.'
            );
        }

        $this->assertPasswordConfirmation($newPassword, $passwordConfirm);

        if (password_verify($newPassword, $account['password'])) {
            throw new InvalidArgumentException('La nueva contraseña debe ser distinta de la actual.');
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_DEFAULT));

        $this->audit->log(
            'password_changed',
            'Auth',
            'El usuario actualizó su contraseña.',
            'success',
            $userId,
            ['role' => $account['role']]
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Exige que la contraseña se repita correctamente para evitar errores de captura.
     */
    private function assertPasswordConfirmation(string $password, string $confirmation): void
    {
        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('Las contraseñas no coinciden.');
        }
    }


    /**
     * Valida el documento de identidad adjuntado y guarda la imagen del reverso.
     *
     * @param array $scan ['data' => json con la lectura, 'file' => imagen subida]
     * @return array<string, mixed>|null Metadatos del documento guardado.
     */
    private function validateIdScan(
        array $scan,
        string $fullName,
        string $document,
        string $birthDate
    ): ?array {
        $payload = $this->decodeScanPayload($scan['data'] ?? null);

        if ($payload === null) {
            if ($this->idScans->isRequired()) {
                throw new InvalidArgumentException(
                    'Adjunta la foto del reverso de tu cédula o tarjeta de identidad para continuar.'
                );
            }

            return null;
        }

        try {
            $record = $this->idScans->validate(
                $payload,
                $fullName,
                $document,
                trim($birthDate) !== '' ? trim($birthDate) : null
            );
        } catch (InvalidArgumentException $exception) {
            $this->audit->log(
                'id_scan_validation_failed',
                'Auth',
                'Validación del documento rechazada: ' . substr($exception->getMessage(), 0, 150),
                'failed',
                null,
                ['cedula' => $this->mask($document), 'role' => 'candidate']
            );

            throw $exception;
        }

        $stored = $this->idScanStorage->store(
            $this->uploadedScanFile($scan),
            isset($payload['image_base64']) ? (string) $payload['image_base64'] : null,
            $record
        );

        $this->audit->log(
            'id_scan_validated',
            'Auth',
            'Documento de identidad leído y validado correctamente.',
            'success',
            null,
            [
                'cedula' => $this->mask($record->documentNumber),
                'role' => 'candidate',
                'source' => $record->source,
                'age' => $record->age(),
            ]
        );

        return $stored;
    }

    /**
     * Interpreta el JSON que envía el lector del documento en el navegador.
     *
     * @return array<string, mixed>|null
     */
    private function decodeScanPayload(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Archivo de la imagen del reverso cuando viene en el formulario.
     */
    private function uploadedScanFile(array $scan): ?array
    {
        $file = $scan['file'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /**
     * Oculta la mayor parte del documento en los registros de auditoría.
     */
    private function mask(string $document): string
    {
        $length = strlen($document);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($document, 0, 2)
            . str_repeat('*', $length - 4)
            . substr($document, -2);
    }
}
