import psycopg2
import pandas as pd
import json
import os
from sklearn.ensemble import RandomForestRegressor

# ================= DB CONNECTION =================
conn = psycopg2.connect(
    host="dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com",
    database="sk_system",
    user="sk_new",
    password="bX9G8vuFr3DTrHIASqTOsK9qCZ6A4lfZ",
    port="5432"
)

# ================= LOAD BARANGAY DATA =================
query = """
SELECT 
    b.id AS barangay_id,

    COALESCE((
        SELECT COUNT(*) FROM projects p 
        WHERE p.barangay_id = b.id AND p.status != 'cancelled'
    ),0) AS total_projects,

    COALESCE((
        SELECT COUNT(*) FROM projects p 
        WHERE p.barangay_id = b.id AND p.status = 'approved'
    ),0) AS approved_projects,

    COALESCE((
        SELECT COUNT(*) FROM projects p 
        WHERE p.barangay_id = b.id AND p.status = 'rejected'
    ),0) AS rejected_projects,

    COALESCE((
        SELECT SUM(amount) FROM budget_transactions bt
        WHERE bt.barangay_id = b.id
    ),0) AS used_budget,

    COALESCE((
        SELECT total_amount FROM budgets bg
        WHERE bg.barangay_id = b.id
        ORDER BY id DESC LIMIT 1
    ),0) AS total_budget,

    COALESCE((
        SELECT AVG(participants) FROM activities a
        WHERE a.barangay_id = b.id
    ),0) AS avg_participants,

    COALESCE((
        SELECT AVG(evaluation_score) FROM activities a
        WHERE a.barangay_id = b.id
    ),0) AS avg_evaluation

FROM barangays b
"""

df = pd.read_sql_query(query, conn)
conn.close()

if df.empty:
    print("No data found")
    exit()

# ================= FEATURE ENGINEERING =================
# ================= NORMALIZED FEATURES (0 to 1 only) =================
df["budget_efficiency"] = (df["used_budget"] / (df["total_budget"] + 1)).clip(0,1)

df["approval_rate"] = (df["approved_projects"] / (df["total_projects"] + 1)).clip(0,1)

df["rejection_rate"] = (df["rejected_projects"] / (df["total_projects"] + 1)).clip(0,1)

df["participation_score"] = (df["avg_participants"] / 100).clip(0,1)

df["quality_score"] = (df["avg_evaluation"] / 100).clip(0,1)

# ================= TRAIN MODEL =================
X = df[[
    "budget_efficiency",
    "approval_rate",
    "rejection_rate",
    "participation_score",
    "quality_score"
]]

y = (
    df["budget_efficiency"] * 25 +
    df["approval_rate"] * 30 +
    (1 - df["rejection_rate"]) * 20 +
    df["participation_score"] * 10 +
    df["quality_score"] * 15
)

model = RandomForestRegressor(n_estimators=200, random_state=42)
model.fit(X, y)

df["predicted_score"] = model.predict(X)

# ================= SAVE RESULTS =================
results = {}

for _, row in df.iterrows():
    score = float(round(max(0, min(100, row["predicted_score"])), 2))

    if score >= 70:
        category = "High Performance"
        prob = 0.85
        rec = "Excellent barangay execution. Expand SK youth programs and maintain funding."
    elif score >= 40:
        category = "Moderate Performance"
        prob = 0.60
        rec = "Stable performance. Improve proposal quality, participation, and monitoring."
    else:
        category = "Low Performance"
        prob = 0.30
        rec = "Needs stronger planning, approval management, and budget utilization."

    results[str(int(row["barangay_id"]))] = {
        "mean_score": score,
        "category": category,
        "success_probability": prob,
        "budget_efficiency_score": score,
        "recommendation": rec
    }

with open("ml_results.json", "w") as f:
    json.dump(results, f, indent=4)

print("ML results generated successfully.")