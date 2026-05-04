from flask import Flask, jsonify
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ================= DB CONNECTION =================
def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST", "dpg-d7ocp6a8qa3s73ahfb4g-a.ohio-postgres.render.com"),
        database=os.getenv("DB_NAME", "sk_system"),
        user=os.getenv("DB_USER", "sk_new"),
        password=os.getenv("DB_PASSWORD", "bX9G8vuFr3DTrHIASqTOsK9qCZ6A4lfZ"),
        port=os.getenv("DB_PORT", "5432")
    )

# ================= LOAD DATA =================
def load_data():
    conn = get_connection()

    query = """
    SELECT 
        COALESCE(participants,0) AS participants,
        COALESCE(allocated_budget,1) AS budget,
        COALESCE(evaluation_score,0) AS evaluation_score
    FROM activities
    WHERE allocated_budget IS NOT NULL
    LIMIT 500
    """

    df = pd.read_sql_query(query, conn)
    conn.close()

    df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
    df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)
    df["evaluation_score"] = pd.to_numeric(df["evaluation_score"], errors="coerce").fillna(0)

    df["efficiency"] = df["participants"] / (df["budget"] + 1)
    df["quality"] = df["evaluation_score"] / 100

    return df

# ================= TRAIN MODEL =================
def train_model(df):
    X = df[["participants", "budget", "efficiency", "quality"]]
    y = (df["efficiency"] * 50) + (df["quality"] * 50)

    model = RandomForestRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)
    return model

# ================= ROOT TEST =================
@app.route("/predict", methods=["GET", "POST"])
def predict():
    try:
        # Allow GET fallback (prevents 405 completely)
        if request.method == "POST":
            data = request.get_json(silent=True)
        else:
            data = request.args.to_dict()

        # Prevent crash if empty request
        if not data:
            data = {"safe": True}

        df = load_data()

        if df.empty:
            return jsonify({
                "status": "error",
                "message": "No data available"
            }), 400

        model = train_model(df)

        X = df[["participants", "budget", "efficiency", "quality"]]
        df["score"] = model.predict(X)

        mean_score = float(df["score"].mean())

        # SIMPLE ROBUST ML LOGIC
        if mean_score >= 70:
            category = "High Performance"
            prob = 0.85
            rec = "Maintain strong programs"
        elif mean_score >= 40:
            category = "Moderate Performance"
            prob = 0.60
            rec = "Improve proposal quality"
        else:
            category = "Low Performance"
            prob = 0.30
            rec = "Improve execution"

        return jsonify({
            "status": "success",
            "mean_score": round(mean_score, 2),
            "category": category,
            "success_probability": prob,
            "budget_efficiency_score": round(mean_score, 2),
            "recommendation": rec
        })

    except Exception as e:
        return jsonify({
            "status": "error",
            "message": str(e)
        }), 500
# ================= RUN (RENDER FIX) =================
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)