import psycopg2
import pandas as pd
import json
import pickle
import os

from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import LinearRegression

# ================= DATABASE CONNECTION =================
def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST", "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com"),
        database=os.getenv("DB_NAME", "sk_system"),
        user=os.getenv("DB_USER", "sk_new"),
        password=os.getenv("DB_PASSWORD", "bX9G8vuFr3DTrHIASqTOsK9qCZ6A4lfZ"),
        port=os.getenv("DB_PORT", "5432")
    )

# ================= LOAD HISTORICAL DATA =================
try:
    conn = get_connection()

    query = """
    SELECT 
        a.id,
        a.title,
        a.barangay_id,
        COALESCE(a.participants,0) AS participants,
        COALESCE(a.evaluation_score,0) AS evaluation_score,
        COALESCE(a.allocated_budget,0) AS allocated_budget,
        COALESCE(b.total_amount,1) AS total_budget,
        COALESCE(b.remaining_budget,0) AS remaining_budget,
        COUNT(p.id) AS project_count
    FROM activities a
    LEFT JOIN budgets b 
        ON a.barangay_id = b.barangay_id
    LEFT JOIN projects p
        ON a.id = p.activity_id
    GROUP BY 
        a.id, a.title, a.barangay_id,
        a.participants, a.evaluation_score,
        a.allocated_budget,
        b.total_amount, b.remaining_budget
    """

    df = pd.read_sql_query(query, conn)
    conn.close()

except Exception as e:
    print("❌ DATABASE ERROR:", e)
    exit()

# ================= CHECK DATA =================
if df.empty:
    print("❌ No historical activity data found.")
    exit()

# ================= CLEAN DATA =================
numeric_cols = [
    "participants",
    "evaluation_score",
    "allocated_budget",
    "total_budget",
    "remaining_budget",
    "project_count"
]

for col in numeric_cols:
    df[col] = pd.to_numeric(df[col], errors="coerce").fillna(0)

df["total_budget"] = df["total_budget"].apply(lambda x: max(x,1))

# ================= FEATURE ENGINEERING =================
df["budget_ratio"] = df["allocated_budget"] / df["total_budget"]
df["cost_per_participant"] = df["allocated_budget"] / (df["participants"] + 1)
df["implementation_strength"] = df["evaluation_score"] * (df["participants"] + 1)
df["budget_utilization"] = ((df["total_budget"] - df["remaining_budget"]) / df["total_budget"]) * 100

# ================= TARGET IMPACT SCORE =================
df["impact_score"] = (
    df["participants"] * 0.30 +
    df["evaluation_score"] * 0.25 +
    (100 - (df["budget_ratio"] * 100)) * 0.20 +
    (100 / (df["cost_per_participant"] + 1)) * 0.15 +
    df["project_count"] * 0.10
)

# ================= FEATURES =================
X = df[[
    "participants",
    "evaluation_score",
    "allocated_budget",
    "budget_ratio",
    "cost_per_participant",
    "implementation_strength",
    "budget_utilization",
    "project_count"
]]

y = df["impact_score"]

print(f"📊 Historical rows loaded: {len(df)}")

# ================= MODEL TRAINING =================
if len(df) < 3:
    print("⚠ Small dataset detected -> Using Linear Regression")
    model = LinearRegression()
else:
    print("✅ Training Random Forest Municipal Intelligence Model...")
    model = RandomForestRegressor(
        n_estimators=150,
        max_depth=12,
        random_state=42
    )

model.fit(X, y)

# ================= PREDICT =================
df["predicted_impact"] = model.predict(X)
df = df.sort_values(by="predicted_impact", ascending=False)

# ================= GENERATE RESULTS =================
results = []

for _, row in df.iterrows():
    recommendation = "Maintain"

    if row["predicted_impact"] >= 80:
        recommendation = "Strongly Recommend Expansion"
    elif row["predicted_impact"] >= 60:
        recommendation = "Recommend Continuation"
    else:
        recommendation = "Needs Improvement"

    results.append({
        "activity_id": int(row["id"]),
        "title": row["title"],
        "barangay_id": int(row["barangay_id"]),
        "participants": int(row["participants"]),
        "evaluation_score": float(row["evaluation_score"]),
        "allocated_budget": float(row["allocated_budget"]),
        "predicted_impact": round(float(row["predicted_impact"]),2),
        "budget_utilization": round(float(row["budget_utilization"]),2),
        "recommendation": recommendation
    })

# ================= SAVE JSON =================
with open("ml_results.json", "w") as f:
    json.dump(results, f, indent=4)

# ================= SAVE MODEL =================
with open("model.pkl", "wb") as f:
    pickle.dump(model, f)

print("✅ Municipal Intelligence Training Completed.")