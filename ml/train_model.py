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
        a.id,
        a.title,
        COALESCE(a.participants, 0) AS participants,
        COALESCE(a.evaluation_score, 0) AS evaluation_score,
        COALESCE(a.allocated_budget, 0) AS allocated_budget,
        b.total_amount AS total_budget
    FROM activities a
    LEFT JOIN budgets b 
        ON a.barangay_id = b.barangay_id
    WHERE b.year = (
        SELECT MAX(year) FROM budgets
    )
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
df["evaluation_score"] = pd.to_numeric(df["evaluation_score"], errors="coerce").fillna(0)
df["allocated_budget"] = pd.to_numeric(df["allocated_budget"], errors="coerce").fillna(0)
df["total_budget"] = pd.to_numeric(df["total_budget"], errors="coerce").fillna(1)

df = df[df["total_budget"] > 0]

# ---------------- FEATURE ENGINEERING ----------------
df["budget_ratio"] = df["allocated_budget"] / df["total_budget"]

df["success_score"] = (
    df["participants"] * 0.5 +
    df["evaluation_score"] * 0.3 +
    df["budget_ratio"] * 100 * 0.2
)

X = df[[
    "participants",
    "evaluation_score",
    "allocated_budget",
    "budget_ratio"
]]

y = df["success_score"]

print(f"📊 Dataset size: {len(df)} rows")

# ---------------- TRAIN MODEL ----------------
if len(df) < 2:
    print("⚠ Small dataset detected. Using Linear Regression fallback.")
    model = LinearRegression()
else:
    print("✅ Training Random Forest model...")
    model = RandomForestRegressor(
        n_estimators=100,
        random_state=42
    )

model.fit(X, y)

# ---------------- PREDICT ----------------
df["predicted_score"] = model.predict(X)

# ---------------- SORT ----------------
df = df.sort_values(by="predicted_score", ascending=False)

# ---------------- SAVE RESULTS ----------------
results = []

for _, row in df.iterrows():
    results.append({
        "activity_id": int(row["id"]),
        "title": row["title"],
        "participants": int(row["participants"]),
        "evaluation_score": float(row["evaluation_score"]),
        "allocated_budget": float(row["allocated_budget"]),
        "predicted_score": round(float(row["predicted_score"]), 2)
    })

with open("ml_results.json", "w") as f:
    json.dump(results, f, indent=4)

# ---------------- SAVE MODEL ----------------
with open("model.pkl", "wb") as f:
    pickle.dump(model, f)

print("✅ ML training completed successfully!")