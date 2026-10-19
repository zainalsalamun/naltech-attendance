# Welcome to Laravel Absensi API Documentation

This API documentation includes all available endpoints, required parameters, and response structures. All endpoints except for `/api/login` require an authorization header in the form of a Bearer Token.

**Base URL**: `http://localhost:8000/api`
**Authorization**: `Bearer {token}`
___

## 1. Authentication

### Login
- **Endpoint**: `POST /login`
- **Description**: Authenticate user and get access token.
- **Payload**:
  - `email` (string, required) - Valid email format.
  - `password` (string, required)
- **Response** (200):
  ```json
  {
      "user": { ... },
      "token": "1|xxxxxxxxxxxxxx"
  }
  ```
- **Error Response** (401):
  ```json
  {
      "message": "Invalid credentials"
  }
  ```

### Logout
- **Endpoint**: `POST /logout`
- **Description**: Revoke the user's current access token.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "message": "Logged out"
  }
  ```

### Update Face Embedding (Profile)
- **Endpoint**: `POST /update-profile`
- **Description**: Update user's face embedding data.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `face_embedding` (string, required)
- **Response** (200):
  ```json
  {
        "message": "Profile updated",
        "user": { ... }
  }
  ```

### Update FCM Token
- **Endpoint**: `POST /update-fcm-token`
- **Description**: Update the user's Firebase Cloud Messaging (FCM) token for push notifications.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `fcm_token` (string, required)
- **Response** (200):
  ```json
  {
      "message": "FCM token updated"
  }
  ```
___

## 2. User Management

### Get Authenticated User
- **Endpoint**: `GET /user`
- **Description**: Get the currently authenticated user's details, including their `shift` relationship.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200): Returns user object.

### Get User by ID
- **Endpoint**: `GET /api-user/{id}`
- **Description**: Fetch specific user by ID.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "status": "Success",
      "message": "User found",
      "data": { ... }
  }
  ```

### Update User Profile Details
- **Endpoint**: `POST /api-user/edit`
- **Description**: Edit user details such as name, email, phone, and profile image.
- **Headers**: `Authorization: Bearer {token}`
- **Payload** (Form-Data):
  - `id` (integer, required)
  - `name` (string, required)
  - `email` (string, required, valid email)
  - `phone` (string, required)
  - `image_url` (file, nullable, image max 2MB)
  - `image` (file, optional if updating profile image)
- **Response** (200):
  ```json
  {
      "status": "Success",
      "message": "Update user success",
      "data": { ... }
  }
  ```
___

## 3. Attendance & QR

### Check In
- **Endpoint**: `POST /checkin`
- **Description**: Record an employee's check-in attendance.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `latitude` (string/float, required)
  - `longitude` (string/float, required)
- **Response** (200):
  ```json
  {
      "message": "Checkin success",
      "attendance": { ... }
  }
  ```

### Check Out
- **Endpoint**: `POST /checkout`
- **Description**: Record an employee's check-out attendance for today.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `latitude` (string/float, required)
  - `longitude` (string/float, required)
- **Error Response** (400 - If not checked in):
  ```json
  {
      "message": "Checkin first"
  }
  ```
- **Response** (200):
  ```json
  {
      "message": "Checkout success",
      "attendance": { ... }
  }
  ```

### Is Checked In?
- **Endpoint**: `GET /is-checkin`
- **Description**: Check whether the authenticated user has checked in and/or checked out today.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "checkedin": true,
      "checkedout": false
  }
  ```

### Get My Attendances
- **Endpoint**: `GET /api-attendances`
- **Description**: List attendances belonging to the authenticated user.
- **Headers**: `Authorization: Bearer {token}`
- **Query Params** (Optional):
  - `date` (string) - Filters the attendance by specific date (YYYY-MM-DD).
- **Response** (200):
  ```json
  {
      "message": "Success",
      "data": [ ... ]
  }
  ```

### Check QR Code Match
- **Endpoint**: `POST /check-qr`
- **Description**: Validates a QR code for check-in or check-out per specific date.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `qr_code` (string, required)
  - `date` (string, required) - Format: 'Y-m-d'
  - `type_qr` (string, required) - 'qr_checkin' or 'qr_checkout'
- **Response** (200 - Valid):
  ```json
  {
      "success": true,
      "status_code": 200,
      "message": "QR code is valid",
      "is_valid": true
  }
  ```
___

## 4. Shifts

### Get All Shifts
- **Endpoint**: `GET /api-shifts`
- **Description**: Retrieve a list of all existing shifts.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "status": "success",
      "data": [ ... ]
  }
  ```

### Create a Shift
- **Endpoint**: `POST /api-shifts`
- **Description**: Add a new shift to the system.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `name` (string, required)
  - `time_in` (string, required) - Time format e.g., "08:00:00"
  - `time_out` (string, required) - Time format e.g., "17:00:00"
- **Response** (201):
  ```json
  {
      "status": "success",
      "data": { ... }
  }
  ```

### Get Specific Shift
- **Endpoint**: `GET /api-shifts/{id}`
- **Description**: Retrieve a shift by ID.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200): Status success and shift data OR 404 (Shift not found).

### Update Shift
- **Endpoint**: `PUT /api-shifts/{id}`
- **Description**: Edit an existing shift.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**: Any fields `name`, `time_in`, `time_out`.
- **Response** (200): Updated shift data OR 404.

___

## 5. Permissions (Leaves/Excuses)

### Create Permission
- **Endpoint**: `POST /api-permissions`
- **Description**: Request for a leave, sick day, or other permission.
- **Headers**: `Authorization: Bearer {token}`
- **Payload** (Form-Data):
  - `date` (date string, required)
  - `reason` (string, required)
  - `image` (file, optional)
- **Response** (201):
  ```json
  {
      "message": "Permission created successfully"
  }
  ```

___

## 6. Reimbursements

### Get My Reimbursements
- **Endpoint**: `GET /api-reimbursements`
- **Description**: Get all reimbursement requests made by the currently authenticated user.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "message": "Success",
      "data": [ ... ]
  }
  ```

### Create Reimbursement Request
- **Endpoint**: `POST /api-reimbursements`
- **Description**: Submit a new reimbursement claim.
- **Headers**: `Authorization: Bearer {token}`
- **Payload** (Form-Data):
  - `date` (date string, required)
  - `description` (string, required)
  - `amount` (numeric, required)
  - `image` (file, nullable, image max 2MB)
- **Response** (201):
  ```json
  {
      "message": "Reimbursement created successfully",
      "data": { ... }
  }
  ```

___

## 7. Notes

### Get My Notes
- **Endpoint**: `GET /api-notes`
- **Description**: Get all personal notes associated with the authenticated user.
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "notes": [ ... ]
  }
  ```

### Create Note
- **Endpoint**: `POST /api-notes`
- **Description**: Add a new personal note.
- **Headers**: `Authorization: Bearer {token}`
- **Payload**:
  - `title` (string, required)
  - `note` (string, required)
- **Response** (201):
  ```json
  {
      "message": "Note created successfully"
  }
  ```

___

## 8. Company

### Get Company Information
- **Endpoint**: `GET /company`
- **Description**: Fetches general company configuration/details (expects company ID = 1).
- **Headers**: `Authorization: Bearer {token}`
- **Response** (200):
  ```json
  {
      "company": { ... }
  }
  ```
