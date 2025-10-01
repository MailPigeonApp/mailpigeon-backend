# MailPigeon Backend (Development)

A local development version of the MailPigeon form submission API built with Leaf PHP.

**Note:** This is a simplified development/testing version. The production API is maintained at [/mailpigeon-api/](https://github.com/MailPigeonApp/mailpigeon-api.git) with additional features like AWS S3 uploads, Telegram integrations, and PostgreSQL database.

## Features

- **Dynamic Form Validation** - Configure fields and validation rules per project
- **API Key Authentication** - Secure Bearer token authentication
- **MySQL Database** - Lightweight local database setup
- **RESTful API** - Simple endpoint structure for CRUD operations
- **CORS Ready** - Frontend integration support

## Quick Start

### Prerequisites

- PHP 8.0+
- Composer
- MySQL database

### Installation

1. Clone the repository:

```bash
git clone <repository-url>
cd mailpigeon-backend
```

2. Install dependencies:

```bash
composer install
```

3. Configure environment variables:

Create a `.env` file in the root directory:

```env
DB_TYPE=mysql
DB_HOST=localhost
DB_USERNAME=your-username
DB_PASSWORD=your-password
DB_NAME=mailpigeon_dev
```

4. Run the development server:

```bash
php -S localhost:8000
```

The API will be available at `http://localhost:8000`

## API Endpoints

### List Users

```http
GET /v1/users
```

**Response:**

```json
[
  {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
]
```

### List Projects

```http
GET /v1/projects
```

**Response:**

```json
[
  {
    "id": 1,
    "name": "Contact Form",
    "fields": "[{\"name\":\"email\",\"type\":\"string\",\"required\":true}]"
  }
]
```

### List Submissions

```http
GET /v1/submissions
```

**Response:**

```json
[
  {
    "id": 1,
    "projectId": 1,
    "data": "{\"email\":\"user@example.com\"}"
  }
]
```

### Submit Form Data

```http
POST /v1/submissions/submit
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "email": "user@example.com",
  "message": "Hello world"
}
```

**Note:** This endpoint currently validates required fields but does not persist data. See [Implementation Status](#implementation-status) below.

## Database Schema

The application expects the following MySQL tables:

### `users`

- `id` - INT (Primary Key, Auto Increment)
- `name` - VARCHAR
- `email` - VARCHAR

### `project`

- `id` - INT (Primary Key, Auto Increment)
- `name` - VARCHAR
- `fields` - JSON - Stores field configuration

### `apikey`

- `key` - VARCHAR (Primary Key)
- `projectId` - INT (Foreign Key)
- `userId` - INT (Foreign Key)

### `submission`

- `id` - INT (Primary Key, Auto Increment)
- `projectId` - INT (Foreign Key)
- `userId` - INT (Foreign Key)
- `data` - JSON - Submission data

## Field Configuration

Fields are configured as JSON in the `project.fields` column:

```json
[
  {
    "name": "email",
    "type": "string",
    "required": true
  },
  {
    "name": "message",
    "type": "string",
    "required": false
  },
  {
    "name": "tags",
    "type": "array",
    "required": false
  }
]
```

## Authentication

All submission endpoints require authentication using Bearer tokens:

```http
Authorization: Bearer YOUR_API_KEY
```

API keys are validated against the `apikey` table in the database.

## Implementation Status

This is a development version with incomplete features:

### 🚧 In Progress

- Form submission persistence
- Proper error response formatting

### 📋 Planned

- Soft delete support
- CORS configuration

## Built With

- [Leaf PHP](https://leafphp.dev/) - Lightweight PHP micro-framework
- [Leaf DB](https://leafphp.dev/modules/db/) - Database ORM
- [Leaf Auth](https://leafphp.dev/modules/auth/) - Authentication utilities
- [phpdotenv](https://github.com/vlucas/phpdotenv) - Environment variable management

### Common Tasks

**Start development server:**

```bash
php -S localhost:8000
```

**Install new dependencies:**

```bash
composer require package/name
```

**Check database connection:**
Visit `http://localhost:8000/v1/users` to verify database connectivity.

## Contributing

When contributing to this repository:

1. Keep compatibility with MySQL
2. Reference production implementation for feature patterns
3. Test all endpoints before committing

## License

This project is licensed under the MIT License.
