from flask import Flask, jsonify
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

# ================= DATABASE CONNECTION =================
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
    try:
        conn = get_connection()

        query = """
        SELECT 
            COALESCE(participants,0) AS participants,
            COALESCE(budget,1) AS budget,
            COALESCE(status,'pending') AS status
        FROM activities
        """

        df = pd.read_sql_query(query, conn)
        conn.close()

        # CLEAN DATA
        df["participants"] = pd.to_numeric(df["participants"], errors="coerce").fillna(0)
        df["budget"] = pd.to_numeric(df["budget"], errors="coerce").fillna(1)

        # FEATURE ENGINEERING
        df["efficiency"] = df["participants"] / df["budget"]

        return df

    except Exception as e:
        print("DB ERROR:", e)
        return pd.DataFrame()

# ================= TRAIN MODEL =================
def train_model(df):
    X = df[["participants", "budget", "efficiency"]]

    # TARGET = efficiency-based learning (stable + meaningful)
    y = df["efficiency"] * 100

    model = RandomForestRegressor(
        n_estimators=100,
        max_depth=8,
        random_state=42
    )

    model.fit(X, y)

    return model

# ================= HOME =================
@app.route("/")
def home():
    return {"status": "ML API Running Successfully"}

# ================= PREDICT =================
@app.route("/predict", methods=["GET", "POST"])
def predict():
    try:
        df = load_data()

        if df.empty:
            return jsonify({
                "error": "No data found in activities table"
            }), 400

        model = train_model(df)

        X = df[["participants", "budget", "efficiency"]]
        df["score"] = model.predict(X)

        avg_score = float(df["score"].mean())

        # ================= INTELLIGENT CLASSIFICATION =================
        if avg_score >= 75:
            category = "High Performing Barangay"
            recommendation = "Scale programs and increase budget allocation"
        elif avg_score >= 50:
            category = "Moderate Performance"
            recommendation = "Optimize project execution and participation"
        else:
            category = "Low Performance Barangay"
            recommendation = "Improve planning, outreach, and engagement"

        return jsonify({
            "category": category,
            "success_probability": round(avg_score / 100, 2),
            "budget_efficiency_score": round(avg_score, 2),
            "recommendation": recommendation,
            "total_records": len(df)
        })

    except Exception as e:
        return jsonify({
            "error": str(e)
        }), 500

# ================= RUN =================
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)