import psycopg2
import pandas as pd
import json
import pickle
import numpy as np
import os

from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import LinearRegression

# ---------------- DATABASE CONNECTION ----------------

conn = psycopg2.connect(
    host=os.getenv("DB_HOST", "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com"),
    database=os.getenv("DB_NAME", "sk_system"),
    user=os.getenv("DB_USER", "sk_admin"),
    password=os.getenv("DB_PASSWORD", "vnEwS9NI5pkc7khmhNCMfvbjbID5YAtm"),
    port=os.getenv("DB_PORT", "5432")
)

# ---------------- QUERY ----------------
query = """
SELECT 
    a.title,
    COALESCE(a.participants, 0) AS participants,
    COALESCE(b.amount, 1) AS budget
FROM activities a
LEFT JOIN budgets b ON b.barangay_id = a.barangay_id
"""

df = pd.read_sql_query(query, conn)

# ---------------- CHECK DATA ----------------
if df.empty:
    print("❌ No data found in database.")
    print("👉 Insert activities + budgets first.")
    exit()

# ---------------- CLEAN DATA ----------------
df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)

df = df[df["budget"] > 0]

# ---------------- FEATURE ENGINEERING ----------------
df["ratio"] = df["participants"] / df["budget"]

df["success_score"] = (
    df["participants"] * 0.7 +
    df["ratio"] * 100
)

X = df[["participants", "budget", "ratio"]]
y = df["success_score"]

print(f"📊 Dataset size: {len(df)} rows")

# ---------------- AUTO TRAIN MODE ----------------
if len(df) < 2:
    print("⚠ Not enough data for ML model. Using fallback prediction model.")

    # fallback model (simple linear regression)
    model = LinearRegression()
    model.fit(X, y)

else:
    print("✅ Training full ML model...")

    model = RandomForestRegressor(
        n_estimators=50,
        random_state=42
    )

    model.fit(X, y)

# ---------------- PREDICTIONS ----------------
df["predicted_score"] = model.predict(X)

# ---------------- SORT RESULTS ----------------
df = df.sort_values(by="predicted_score", ascending=False)

# ---------------- SAVE JSON OUTPUT ----------------
results = []

for _, row in df.iterrows():
    results.append({
        "title": row["title"],
        "participants": float(row["participants"]),
        "budget": float(row["budget"]),
        "predicted_score": round(float(row["predicted_score"]), 2)
    })

with open("ml_results.json", "w") as f:
    json.dump(results, f, indent=4)

# ---------------- SAVE MODEL ----------------
with open("model.pkl", "wb") as f:
    pickle.dump(model, f)

print("✅ ML training completed (AUTO MODE SAFE)")