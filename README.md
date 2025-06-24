# Shopping List App

Eine Shopping-Liste-Anwendung mit PHP/Slim Backend, PostgreSQL Datenbank und Flask Frontend.

## Technologie-Stack

- **Backend**: PHP 8.1 + Slim Framework
- **Datenbank**: PostgreSQL 15
- **Frontend**: Flask (Python)
- **Container**: Docker + Kubernetes

## Docker Compose Setup

```bash
# Anwendung starten
docker-compose up -d

# Status prüfen
docker-compose ps
```

**Services:**
- Frontend: http://localhost:5000
- API: http://localhost:8080
- PostgreSQL: localhost:5432

## Kubernetes Setup

```bash
# Docker Image bauen
cd backend
docker build -t shopping-api:latest .
cd ..

# Deployment
kubectl apply -f k8s/postgres.yml
kubectl wait --for=condition=ready pod -l app=postgres --timeout=300s
kubectl apply -f k8s/api.yml
kubectl apply -f k8s/frontend.yml

# Zugriff
kubectl port-forward service/shopping-frontend-service 5000:5000
kubectl port-forward service/shopping-api-service 8080:8000
```

## API Endpunkte

| Method | Endpoint | Beschreibung |
|--------|----------|-------------|
| GET | `/items` | Alle Items abrufen |
| POST | `/items` | Neues Item erstellen |
| PUT | `/items/{id}` | Item aktualisieren |
| DELETE | `/items/{id}` | Item löschen |

## Beispieldaten

```sql
INSERT INTO items (name, quantity) VALUES 
    ('Apples', 10),
    ('Bananas', 5),
    ('Milk', 2),
    ('Bread', 1),
    ('Eggs', 12);
```

## 12-Factor Principles Applied

1. **Codebase**: Ein Git-Repository für die gesamte Anwendung.
2. **Dependencies**: Alle Abhängigkeiten sind klar definiert (composer.json, Dockerfile).
3. **Configuration**: Konfigurationen werden über Umgebungsvariablen bereitgestellt (DB_HOST, DB_NAME, etc.).
4. **Backing Services**: PostgreSQL wird über Umgebungsvariablen konfiguriert.
5. **Build, Release, Run**: Docker-Container trennen Build, Release und Run-Phasen.
6. **Processes**: Die Anwendung läuft als stateless Prozesse in Containern.
7. **Port Binding**: Services binden sich an konfigurierte Ports.
8. **Concurrency**: Skalierbarkeit durch Kubernetes Replicas unterstützt.
9. **Disposability**: Container können jederzeit gestartet oder gestoppt werden.
10. **Dev/Prod Parity**: Entwicklungs- und Produktionsumgebung sind durch Docker identisch.
11. **Logs**: Logs werden in der Standardausgabe der Container gespeichert.
12. **Admin Processes**: Verwaltungsaufgaben werden durch Container durchgeführt.# Puvs_lab
