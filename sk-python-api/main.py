from fastapi import FastAPI
from pathlib import Path
import os
import requests

app = FastAPI()

# ---------- MANUAL .ENV READ ----------
env_path = Path(__file__).parent / ".env"
api_key = None

if env_path.exists():
    with open(env_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("BREVO_API_KEY=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"):
                api_key = line.split("=", 1)[1].strip()

print("API KEY LOADED:", api_key)


@app.post("/send-email")
def send_email(data: dict):

    if not api_key:
        return {"success": False, "message": "Missing API key"}

    url = "https://api.brevo.com/v3/smtp/email"

    payload = {
        "sender": {
            "name": "SK System",
            "email": data["sender_email"]
        },
        "to": [
            {
                "email": data["to_email"],
                "name": data["to_name"]
            }
        ],
        "subject": data["subject"],
        "htmlContent": data["html"]
    }

    headers = {
        "accept": "application/json",
        "api-key": api_key,
        "content-type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)

    return {
        "success": response.status_code in [200, 201],
        "response": response.json()
    }