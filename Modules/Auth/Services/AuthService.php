<?php

namespace Modules\Auth\Services;

use InvalidArgumentException;
use Modules\Auditoria\Services\AuditService;
use Modules\Auth\Repositories\AuthRepository;
use Modules\Identity\Services\CedulaValidationService;
use RuntimeException;

final class AuthService
{
    private const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private AuthRepository $users,
        private AuditService $audit,
        private CedulaValidationService $cedulas
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->users->findByEmail($normalizedEmail);

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

    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $cedula,
        string $birthDate
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

        $this->validateCedula($document, $normalizedName, $birthDate, 'candidate');

        $userId = $this->users->createCandidate(
            $normalizedName,
            $normalizedEmail,
            password_hash($password, PASSWORD_DEFAULT),
            $document
        );

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

    public function registerCompany(
        string $name,
        string $email,
        string $password,
        string $passwordConfirm,
        string $companyName,
        string $city,
        string $cedula,
        string $birthDate = ''
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

        $this->validateCedula($document, $normalizedName, $birthDate !== '' ? $birthDate : null, 'recruiter');

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
     * Valida la cédula contra el registro de identidad y deja rastro de auditoría.
     */
    private function validateCedula(
        string $document,
        string $fullName,
        ?string $birthDate,
        string $role
    ): void {
        try {
            $record = $this->cedulas->validate($document, $fullName, $birthDate, $role);
        } catch (InvalidArgumentException $exception) {
            $this->audit->log(
                'cedula_validation_failed',
                'Auth',
                'Validación de cédula rechazada: ' . substr($exception->getMessage(), 0, 150),
                'failed',
                null,
                ['cedula' => $this->mask($document), 'role' => $role]
            );

            throw $exception;
        } catch (RuntimeException $exception) {
            $this->audit->log(
                'cedula_api_error',
                'Auth',
                'La API de cédulas no respondió: ' . substr($exception->getMessage(), 0, 150),
                'error',
                null,
                ['cedula' => $this->mask($document), 'role' => $role]
            );

            if (!$this->cedulas->allowsFailOpen()) {
                throw new InvalidArgumentException(
                    'No fue posible validar tu cédula en este momento. Intenta de nuevo más tarde.'
                );
            }

            return;
        }

        $this->audit->log(
            'cedula_validated',
            'Auth',
            'Cédula validada correctamente contra el registro de identidad.',
            'success',
            null,
            [
                'cedula' => $this->mask($document),
                'role' => $role,
                'source' => $record->source,
            ]
        );
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
