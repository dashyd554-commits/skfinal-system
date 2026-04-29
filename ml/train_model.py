import psycopg2
import pandas as pd
import json
import pickle
import os

from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import LinearRegression

# ---------------- SAFE DB CONNECT ----------------
def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST", "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com"),
        database=os.getenv("DB_NAME", "sk_system"),
        user=os.getenv("DB_USER", "sk_new"),
        password=os.getenv("DB_PASSWORD", "bX9G8vuFr3DTrHIASqTOsK9qCZ6A4lfZ"),
        port=os.getenv("DB_PORT", "5432")
    )

# ---------------- LOAD DATA ----------------
try:
    conn = get_connection()

    query = """
    SELECT 
        a.title,
        COALESCE(a.participants, 0) AS participants,
        COALESCE(
            (SELECT total_amount FROM budgets ORDER BY id DESC LIMIT 1),
            1
        ) AS budget
    FROM activities a
    """

    df = pd.read_sql_query(query, conn)
    conn.close()

except Exception as e:
    print("❌ DATABASE ERROR:", e)
    exit()

# ---------------- CHECK DATA ----------------
if df.empty:
    print("❌ No data found in database.")
    print("👉 Insert activities first.")
    exit()

# ---------------- CLEAN DATA ----------------
df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)

df = df[df["budget"] > 0]

if df.empty:
    print("❌ Data invalid after cleaning.")
    exit()

# ---------------- FEATURE ENGINEERING ----------------
df["ratio"] = df["participants"] / df["budget"]

df["success_score"] = (
    df["participants"] * 0.7 +
    df["ratio"] * 100
)

X = df[["participants", "budget", "ratio"]]
y = df["success_score"]

print(f"📊 Dataset size: {len(df)} rows")

# ---------------- AUTO TRAIN SAFE ----------------
if len(df) < 2:
    print("⚠ Small dataset detected. Using LinearRegression fallback.")
    model = LinearRegression()
    model.fit(X, y)
else:
    print("✅ Training RandomForest model...")
    model = RandomForestRegressor(n_estimators=50, random_state=42)
    model.fit(X, y)

# ---------------- PREDICT ----------------
df["predicted_score"] = model.predict(X)

# ---------------- SORT ----------------
df = df.sort_values(by="predicted_score", ascending=False)

# ---------------- SAVE JSON ----------------
results = []

for _, row in df.iterrows():
    results.append({
        "title": row["title"],
        "participants": int(row["participants"]),
        "budget": float(row["budget"]),
        "predicted_score": round(float(row["predicted_score"]), 2)
    })

with open("ml_results.json", "w") as f:
    json.dump(results, f, indent=4)

# ---------------- SAVE MODEL ----------------
with open("model.pkl", "wb") as f:
    pickle.dump(model, f)

print("✅ ML training completed successfully!")