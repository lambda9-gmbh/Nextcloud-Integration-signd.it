# signd API - Analysis

> See also: [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md)
>
> **OpenAPI Spec (source of truth):** `../digisign/src/main/resources/static/api.yaml`

**Base URL:** (configurable, e.g. `https://signd.it`)
**API Specification:** OpenAPI 3.0.3
**Authentication:** `X-API-KEY` header (per account)

## Endpoints

### Authentication & Account
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v2/api-login` | Login with email/password → API key + user data + permissions |
| POST | `/api/register-account` | Register new signd account → accountId + userId + API key |
| POST | `/api/prices` | Retrieve price info for product plans (premium/enterprise) |

### Process Creation
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/new` | Create new signature process (fully via API) |
| POST | `/api/start-wizard` | Create draft → wizard URL to complete in signd UI |
| POST | `/api/resume-wizard` | Resume draft wizard → URL |
| POST | `/api/cancel-wizard` | Delete draft |

### Process Management
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/cancel-process` | Cancel process (with reason) |
| POST | `/api/resume-process` | Resume interrupted process |
| POST | `/api/update-client-metadata` | Update metadata of a process |

### Process Query
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/get-meta?id=...` | Metadata of a process/draft by ID |
| GET | `/api/list` | Search/filter processes (paginated, many filters) |
| GET | `/api/list-status` | Process status info (compact) |
| GET | `/api/new-finished?gt=...` | IDs of finished processes after timestamp |
| POST | `/api/find-by-original` | Find process by original PDF |

### Document Download
| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/finished?id=...` | Download finished PDF |

### Utility Functions
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/upload-check` | Check PDF for signature integrity → redirect URL |
| POST | `/api/base64-encode` | Binary data → Base64 |
| POST | `/api/base64-decode` | Base64 → binary data |
| GET | `/api/user-info` | Info about API user |

### Callback / Notification
| Method | Path | Description |
|--------|------|-------------|
| POST | (configurable URL) | Webhook notification on events |

## Key Data Models

### NewDocumentRequest (Create Process)
**Required fields:**
- `pdfFilename` - PDF filename
- `pdfData` - PDF as Base64 string
- `signersMode` - `SEQUENCE` (one document, sequential) or `SPREAD` (each signer gets own copy)

**Optional fields:**
- `initiatorName`, `initiatorEmail` - Sender info
- `name` - Process name
- `emailRecipients[]` / `mobileRecipients[]` - Signers (via email/SMS)
- `individualInvitation/Reminder/Cancellation/Completion` - Custom texts
- `pdfPassword` / `generatePassword` - PDF password protection
- `callbackUrl` - Webhook for status updates
- `protocolPlacement` - Protocol at beginning/end/none
- `deadline` - Global deadline
- `apiClientMetaData` - Application-specific metadata (JSON)
- `individualEmailSenderAddress/Name` - Custom sender address (with custom SMTP)

### RecipientData (Signer)
- `sequence` (required) - Order/grouping
- `clearName` - Name
- `accessCode` - Optional access code
- `inlineSignaturePlacements[]` - Signature positioning in the PDF
- `stampMode` - `required` / `optional` / `none`
- `additionalInputs[]` - Additional input fields

### EmailRecipient / MobileRecipient
- Inherits from RecipientData
- + `email` or `mobile`

### FoundProcess (Process Result)
- `documentId`, `multiProcessKey`, `secretKey`
- `name`, `created`, `initiator`, `filename`
- `signersCompleted[]`, `signersRejected[]`, `signersPending[]`
- `apiClientMetaData`
- `lastSignerAction`, `interrupted`, `cancelled`

### SignerCompleted
- `id`, `clearName`, `email`, `location`
- `signed`, `signedTimezone`, `invited`
- `stampMode`, `additionalInputs[]`
- `signatureKey` - Key to retrieve the (animated) signature image from signd

### SignerRejected
- `id`, `rejected`, `rejectedTimezone`, `rejectedReason`
- `rejectionByCancellation`, `invited`, `stampMode`

### SignerPending
- `id`, `sequence`, `accessCode`, `stampMode`

### CreatedDocument (Response to `/api/new`)
- `id` - Document ID
- `hash` - SHA-512 of the original (Base64)
- `signerUrls[]` - Sign URLs per signer
- `password` (optional, if generated)
- `warnings[]`
- `additionalIdsCreated[]` (in SPREAD mode)

### Notification (Webhook)
- `trigger` - `SIGNED` | `REJECTED` | `FINISHED` | `CANCELLED`
- `process` - FoundProcess object
- `signer` - UUID of the triggering signer (for SIGNED/REJECTED)

## Process Modes

### SEQUENCE
All participants sign **the same** document. The `sequence` number determines the order. Next signer is only invited when previous ones are done.

### SPREAD
Each participant (or group with the same sequence number) gets their **own copy** of the original document.

## API Features Relevant for the NC App

### For the overview page:
- `GET /api/list` - Paginated list with many filters (status, date, name, metadata search)
- `GET /api/list-status` - Compact status info
- Filters: `status`, `processName`, `fileName`, `initiatorName`, `signerName`, `searchQuery`, `dateFrom/To`, `metadataSearch`
- Sorting: `PROCESS_NAME`, `INITIATOR`, `FILENAME`, `CREATED`, `LAST_SIGNER_ACTION` + ASC/DESC

### For the sidebar tab:
- `POST /api/find-by-original` - Send PDF file → find matching processes
- `GET /api/get-meta?id=...` - Detail info for known process
- `GET /api/finished?id=...` - Download finished PDF

### For process creation:
- `POST /api/new` - Full process via API
- `POST /api/start-wizard` - Draft + redirect to signd wizard UI

### For metadata tracking:
- `apiClientMetaData` set on process creation (e.g. NC file ID, path, user)
- `metadataSearch` on `/api/list` to filter by NC-specific metadata
- `POST /api/update-client-metadata` to update

### For status changes:
- `callbackUrl` set on creation → webhook to NC app
- Alternative: Polling via `GET /api/list-status` or `GET /api/new-finished?gt=...`

## Authentication & Account Management
- Every request (except `/api/prices` and `/api/register-account`) requires `X-API-KEY` in the header
- API key is obtained via:
  - Login (`/api/v2/api-login`) with email/password
  - Registration (`/api/register-account`) - creates new account + returns API key
  - Via the signd UI (manually)
- Key is account-bound

## Pricing Model

### Endpoint: `POST /api/prices`
- No API key required
- Returns prices for `premium` and `enterprise` plans

### PriceInfo Structure
- `perProcess` - Price per signature process (after included quota is used up)
- `perMonthAndUser` - Monthly price per user
- `includedProcessesPerMonth` - Included processes per month
- `sms` - Price per SMS invitation
- `qes` - Price per qualified electronic signature

## Account Registration

### Endpoint: `POST /api/register-account`
- No API key required (it creates the account)

### RegisterAccountRequest
**Fields:**
- `productPlan` - `premium` or `enterprise`
- `organisation` - Organization name
- `street`, `houseNumber`, `zipCode`, `city` - Address
- `country` - Country code (default: `DE`)
- `clearName` - Account holder name
- `email` - Email address
- `password` - Password
- `vatId` - VAT ID (optional)
- `couponCode` - Coupon code (optional)
- `agbAccepted` - ToS accepted (boolean)
- `dsbAccepted` - Privacy policy accepted (boolean)
- `dlvSend` - DPA sent (boolean)

### RegisterAccountResponse
- `accountId` - UUID of the created account
- `userId` - Internal user ID (int64)
- `apiKey` - Generated API key for further requests