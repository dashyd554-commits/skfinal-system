from flask import Flask, jsonify, request
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ================= DB CONNECTION =================
def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST"),
        database=os.getenv("DB_NAME"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        port=os.getenv("DB_PORT", "5432")
    )

# ================= LOAD DATA =================
def load_data(barangay_id=None):
    conn = get_connection()

    # Try activities table first with flexible column names
    # Falls back to projects table if activities has no useful data
    query = """
    SELECT 
        COALESCE(a.participants, 0)                          AS participants,
        COALESCE(a.budget_allocated,
                 a.allocated_budget,
                 a.budget_requested, 1)                      AS budget,
        COALESCE(a.evaluation_score, 0)                      AS evaluation_score
    FROM activities a
    WHERE COALESCE(a.budget_allocated,
                   a.allocated_budget,
                   a.budget_requested, 0) > 0
    {barangay_filter}
    LIMIT 500
    """

    params = []
    if barangay_id:
        query = query.format(barangay_filter="AND a.barangay_id = %s")
        params = [barangay_id]
    else:
        query = query.format(barangay_filter="")

    df = pd.read_sql_query(query, conn, params=params if params else None)

    # If activities has no data, fall back to projects table
    if df.empty or df['participants'].sum() == 0:
        fallback_query = """
        SELECT
            COALESCE(p.target_participants, 0)  AS participants,
            COALESCE(p.budget_requested, 1)     AS budget,
            CASE
                WHEN p.status = 'approved' THEN 80
                WHEN p.status = 'rejected' THEN 20
                ELSE 50
            END                                  AS evaluation_score
        FROM projects p
        WHERE p.budget_requested > 0
        {barangay_filter}
        LIMIT 500
        """
        if barangay_id:
            fallback_query = fallback_query.format(barangay_filter="AND p.barangay_id = %s")
            df = pd.read_sql_query(fallback_query, conn, params=[barangay_id])
        else:
            fallback_query = fallback_query.format(barangay_filter="")
            df = pd.read_sql_query(fallback_query, conn)

    conn.close()

    df["participants"]      = pd.to_numeric(df["participants"],      errors="coerce").fillna(0)
    df["budget"]            = pd.to_numeric(df["budget"],            errors="coerce").fillna(1)
    df["evaluation_score"]  = pd.to_numeric(df["evaluation_score"],  errors="coerce").fillna(0)

    # Prevent division by zero
    df["budget"] = df["budget"].replace(0, 1)

    df["efficiency"] = df["participants"] / df["budget"]
    df["quality"]    = df["evaluation_score"] / 100

    return df

# ================= TRAIN MODEL =================
def train_model(df):
    X = df[["participants", "budget", "efficiency", "quality"]]
    y = (df["efficiency"] * 50) + (df["quality"] * 50)

    # Clamp scores to 0–100
    y = y.clip(0, 100)

    model = RandomForestRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)
    return model

# ================= ROOT TEST =================
@app.route("/")
def home():
    return jsonify({
        "status": "running",
        "message": "Flask ML API is working"
    })

# ================= PREDICT API =================
# Supports:  GET /predict
#            GET /predict?barangay_id=3
@app.route("/predict")
def predict():
    try:
        barangay_id = request.args.get("barangay_id", None)
        if barangay_id:
            barangay_id = int(barangay_id)

        df = load_data(barangay_id)

        if df.empty:
            return jsonify({
                "status": "error",
                "mean_score": 0,
                "row_count": 0,
                "message": "No activity or project data found for this barangay."
            }), 400

        model   = train_model(df)
        X       = df[["participants", "budget", "efficiency", "quality"]]
        scores  = model.predict(X)
        scores  = scores.clip(0, 100)

        mean_score = float(scores.mean())

        return jsonify({
            "status":     "ok",
            "mean_score": round(mean_score, 2),
            "row_count":  len(df),
            "barangay_id": barangay_id
        })

    except Exception as e:
        return jsonify({
            "status":  "error",
            "message": str(e),
            "mean_score": 0
        }), 500


# ================= RUN =================
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)