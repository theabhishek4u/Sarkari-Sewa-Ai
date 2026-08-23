# Sarkari Sewa AI — REST API Documentation

The plugin registers custom REST API endpoints under namespace `sarkari-sewa-ai/v1`.

---

## 1. Chat Completion Endpoint

* **Route:** `POST /wp-json/sarkari-sewa-ai/v1/chat`
* **Permission:** Public (Rate-limited per IP)
* **Headers:** `Content-Type: application/json`

### Request Body
```json
{
  "message": "Railway ki vacancy aayi hai?"
}
```

### Success Response (200 OK)
```json
{
  "success": true,
  "data": {
    "answer": "हाँ, Sarkari Sewa पर RRC NCR Prayagraj Apprentice 2026 की भर्ती उपलब्ध है। आवेदन की अंतिम तिथि 15 सितंबर 2026 है।",
    "has_results": true,
    "results": [
      {
        "post_id": 1234,
        "title": "RRC NCR Prayagraj Apprentice Recruitment 2026",
        "url": "https://sarkariseva.com/rrc-ncr-apprentice-2026/",
        "category": "Latest Jobs",
        "last_date": "2026-09-15",
        "relevance": 0.92,
        "published_at": "20 Aug 2026"
      }
    ]
  }
}
```

### No-Result Response (200 OK — Grounding Protected)
```json
{
  "success": true,
  "data": {
    "answer": "मुझे Sarkari Sewa पर इस विषय से संबंधित कोई पोस्ट या सूचना नहीं मिली। मैं केवल SarkariSewa.com पर प्रकाशित आधिकारिक जानकारी के आधार पर उत्तर दे सकता हूँ।",
    "has_results": false,
    "results": []
  }
}
```

### Rate Limited Response (429 Too Many Requests)
```json
{
  "success": false,
  "error": "Too many requests. Please wait a minute before asking again.",
  "code": "rate_limit_exceeded"
}
```

---

## 2. Status & Health Endpoint

* **Route:** `GET /wp-json/sarkari-sewa-ai/v1/status`
* **Permission:** Public

### Response (200 OK)
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "version": "1.0.0",
    "site_url": "https://sarkariseva.com",
    "total_indexed": 5284,
    "total_chunks": 18940,
    "last_sync": "23 Aug 2026, 04:00 AM"
  }
}
```

---

## 3. Search Debugging Endpoint (Admin Only)

* **Route:** `POST /wp-json/sarkari-sewa-ai/v1/search`
* **Permission:** `manage_options` (Admin)

### Request Body
```json
{
  "query": "SSC result"
}
```

---

## 4. Bulk Reindex Batch Endpoint (Admin Only)

* **Route:** `POST /wp-json/sarkari-sewa-ai/v1/reindex`
* **Permission:** `manage_options` (Admin)

### Request Body
```json
{
  "offset": 0,
  "batch_size": 25
}
```
