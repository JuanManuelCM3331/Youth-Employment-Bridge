#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) - Módulo de Analítica de Empleo
Issue #01 [Data/Setup]: Script de verificación de entorno reproducible y rutas relativas.

Cubre:
  - CA-ISS-01-01: Verificación de estructura de carpetas y requirements.txt
  - CA-ISS-01-02: Validación de rutas relativas con pathlib.Path y versiones
  - CA-ISS-01-03: Matriz de preguntas analíticas del dominio YEB
  - CA-ISS-01-04: Demostración de anonimización ética para menores (16-17 años)
  - Casos de prueba: CP-041, CP-041B, CP-041C
"""

from __future__ import annotations

import hashlib
import importlib.metadata
import os
import platform
import subprocess
import sys
from pathlib import Path


def _ensure_venv_interpreter() -> None:
    """Si se ejecuta desde el Python global (ej. botón Run de VS Code) y existe analytics/.venv,
    re-ejecuta automáticamente el script usando el intérprete aislado de analytics/.venv."""
    script_path = Path(__file__).resolve()
    analytics_root = script_path.parent.parent if script_path.parent.name == "scripts" else Path.cwd()
    venv_python_win = analytics_root / ".venv" / "Scripts" / "python.exe"
    venv_python_unix = analytics_root / ".venv" / "bin" / "python"
    venv_python = venv_python_win if venv_python_win.exists() else (venv_python_unix if venv_python_unix.exists() else None)

    if venv_python is not None:
        current_exe = Path(sys.executable).resolve()
        target_exe = venv_python.resolve()
        if current_exe != target_exe and os.environ.get("YEB_VENV_REDIRECTED") != "1":
            env = os.environ.copy()
            env["YEB_VENV_REDIRECTED"] = "1"
            result = subprocess.run([str(target_exe), str(script_path), *sys.argv[1:]], env=env)
            raise SystemExit(result.returncode)


def resolve_analytics_root() -> Path:
    """Resuelve la raíz del subentorno analytics/ usando exclusivamente pathlib.Path."""
    current_path = Path(__file__).resolve()
    # Si se ejecuta desde analytics/scripts/01_verificar_entorno.py -> parent.parent es analytics/
    if current_path.parent.name == "scripts":
        return current_path.parent.parent
    return Path.cwd()


def verify_directory_structure(analytics_root: Path) -> dict[str, Path]:
    """Verifica la existencia de todas las carpetas y archivos exigidos en CA-ISS-01-01."""
    required_dirs = {
        "data_raw": Path("data") / "raw",
        "data_processed": Path("data") / "processed",
        "notebooks": Path("notebooks"),
        "scripts": Path("scripts"),
        "outputs": Path("outputs"),
        "docs": Path("docs"),
    }
    required_files = {
        "requirements": Path("requirements.txt"),
        "ethics_doc": Path("docs") / "matriz_preguntas_etica_yeb.md",
    }

    resolved_paths: dict[str, Path] = {}

    print("=" * 78)
    print("1. VALIDACIÓN DE ESTRUCTURA REPRODUCIBLE (pathlib.Path)")
    print("=" * 78)
    print(f"Raíz del subentorno detectada : {analytics_root.name}/")

    for key, rel_dir in required_dirs.items():
        target = analytics_root / rel_dir
        assert target.exists() and target.is_dir(), (
            f"[ERROR] Directorio faltante: {rel_dir}"
        )
        resolved_paths[key] = target
        print(f"  [OK] Directorio verificado : {rel_dir.as_posix()}/")

    for key, rel_file in required_files.items():
        target = analytics_root / rel_file
        assert target.exists() and target.is_file(), (
            f"[ERROR] Archivo requerido faltante: {rel_file}"
        )
        resolved_paths[key] = target
        print(f"  [OK] Archivo verificado    : {rel_file.as_posix()}")

    return resolved_paths


def verify_library_versions() -> dict[str, str]:
    """Verifica e informa las versiones de las librerías núcleo (CA-ISS-01-02)."""
    print("\n" + "=" * 78)
    print("2. VERIFICACIÓN DE VERSIONES DEL ENTORNO ANALÍTICO")
    print("=" * 78)
    print(f"  Python     : {sys.version.split()[0]} ({platform.system()} {platform.release()})")

    packages = ["pandas", "numpy", "jupyter_core", "notebook", "Faker"]
    versions: dict[str, str] = {}

    for pkg in packages:
        try:
            ver = importlib.metadata.version(pkg)
            versions[pkg] = ver
            print(f"  {pkg:<12}: v{ver} [OK]")
        except importlib.metadata.PackageNotFoundError as exc:
            raise RuntimeError(
                f"La librería requerida '{pkg}' no está instalada. "
                "Ejecute: pip install -r analytics/requirements.txt"
            ) from exc

    return versions


def build_analytical_questions_matrix():
    """Construye la matriz de preguntas analíticas de YEB como DataFrame (CA-ISS-01-03)."""
    import pandas as pd

    print("\n" + "=" * 78)
    print("3. MATRIZ DE PREGUNTAS ANALÍTICAS INICIALES - DOMINIO YEB")
    print("=" * 78)

    questions = [
        {
            "ID": "PA-01",
            "Dimension": "Embudo de selección",
            "Pregunta": "¿Cuál es la tasa de conversión entre estados (pending -> review -> interview -> accepted/rejected)?",
            "Tablas_YEB": "applications, users",
            "Metrica_Clave": "Tasa de avance por etapa (%)",
        },
        {
            "ID": "PA-02",
            "Dimension": "Tiempo promedio de contratación",
            "Pregunta": "¿Cuántos días tarda en promedio una postulación desde su registro hasta entrevista o cierre?",
            "Tablas_YEB": "applications, jobs, companies",
            "Metrica_Clave": "tiempo_respuesta_dias (media y mediana)",
        },
        {
            "ID": "PA-03",
            "Dimension": "Tasa de postulaciones por vacante",
            "Pregunta": "¿Cuántas postulaciones recibe cada vacante activa según modalidad y nivel de experiencia?",
            "Tablas_YEB": "jobs, applications",
            "Metrica_Clave": "Postulaciones / Vacante publicada",
        },
        {
            "ID": "PA-04",
            "Dimension": "Brecha por ciudad y salario",
            "Pregunta": "¿Qué desajuste existe entre la oferta salarial/geográfica de las vacantes y la demanda juvenil (16-24 años)?",
            "Tablas_YEB": "jobs, users, cities",
            "Metrica_Clave": "Ratio Candidatos/Vacantes y Brecha Salarial (COP)",
        },
    ]

    df_matrix = pd.DataFrame(questions)
    for _, row in df_matrix.iterrows():
        print(f"  [{row['ID']}] {row['Dimension']}:")
        print(f"         Pregunta : {row['Pregunta']}")
        print(f"         Métrica  : {row['Metrica_Clave']} (Fuente: {row['Tablas_YEB']})")

    return df_matrix


def demonstrate_ethical_anonymization():
    """Demuestra el protocolo de minimización y anonimización para menores 16-17 años (CA-ISS-01-04)."""
    from faker import Faker

    print("\n" + "=" * 78)
    print("4. PROTOCOLO ÉTICO: MINIMIZACIÓN Y ANONIMIZACIÓN (MENORES 16-17 AÑOS)")
    print("=" * 78)

    fake = Faker("es_CO")
    Faker.seed(42)

    # Simulación de un registro crudo sensible en BD transaccional (nunca exportado en claro)
    raw_sensitive_candidate = {
        "user_id": 104,
        "name": "Camila Andrea Gómez Pérez",
        "email": "camila.gomez17@correo.com",
        "cedula": "1098765432",
        "phone": "3001234567",
        "birth_date": "2009-05-14",
        "age": 17,
        "is_minor": 1,
        "minor_blocked": 0,
        "location": "Medellín",
        "skills": "Python, SQL, Excel",
        "cv_data_longblob": b"<BYTES_PDF_PRIVADO>",
        "minor_authorization_blob": b"<BYTES_PERMISO_PADRES>",
    }

    # Función de anonimización y minimización hacia data/raw/
    salt = "yeb_analytics_salt_2026"
    pseudo_id = "CAND-" + hashlib.sha256(
        f"{raw_sensitive_candidate['user_id']}:{salt}".encode("utf-8")
    ).hexdigest()[:12]

    anonymized_record = {
        "candidate_hash": pseudo_id,
        "synthetic_doc_id": f"DOC-SYN-{fake.unique.random_number(digits=6, fix_len=True)}",
        "age": raw_sensitive_candidate["age"],
        "age_cohort": "Menor Protegido (16-17)"
        if raw_sensitive_candidate["is_minor"] == 1
        else "Joven Adulto (18-24)",
        "is_minor": bool(raw_sensitive_candidate["is_minor"]),
        "minor_authorized": raw_sensitive_candidate["is_minor"] == 1
        and raw_sensitive_candidate["minor_blocked"] == 0,
        "city": raw_sensitive_candidate["location"],
        "skills": raw_sensitive_candidate["skills"],
    }

    # Invariantes éticos de verificación (CP-041C / LEG-001)
    forbidden_keys = {
        "name",
        "email",
        "cedula",
        "phone",
        "birth_date",
        "cv_data_longblob",
        "minor_authorization_blob",
    }
    assert forbidden_keys.isdisjoint(anonymized_record.keys()), (
        "Violación de minimización: se encontraron atributos PII directos."
    )
    assert 16 <= anonymized_record["age"] <= 24, (
        "Edad fuera del rango juvenil permitido en YEB (16-24 años)."
    )

    print("  [OK] Atributos PII directos y binarios LONGBLOB excluidos en origen.")
    print(f"  [OK] Registro seudonimizado de prueba: {anonymized_record}")
    return anonymized_record


def main() -> int:
    _ensure_venv_interpreter()
    analytics_root = resolve_analytics_root()
    verify_directory_structure(analytics_root)
    verify_library_versions()
    build_analytical_questions_matrix()
    demonstrate_ethical_anonymization()

    print("\n" + "=" * 78)
    print("RESULTADO FINAL: ENTORNO ANALÍTICO YEB VERIFICADO EXITOSAMENTE (CP-041 / CP-041B / CP-041C)")
    print("=" * 78)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
