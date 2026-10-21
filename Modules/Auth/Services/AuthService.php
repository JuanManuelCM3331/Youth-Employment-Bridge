<?php

namespace Modules\Auth\Services;

use InvalidArgumentException;
use Modules\Auditoria\Services\AuditService;
use Modules\Auth\Repositories\AuthRepository;
use Modules\Identity\Services\CedulaValidationService;
use RuntimeException;

final class AuthService
{
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

        if (strlen($password) < 6) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos 6 caracteres.'
            );
        }

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

        if (strlen($password) < 6) {
            throw new InvalidArgumentException(
                'La contraseña debe tener al menos 6 caracteres.'
            );
        }

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
