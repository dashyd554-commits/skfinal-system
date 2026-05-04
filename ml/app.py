from flask import Flask, jsonify
import pandas as pd
import psycopg2
import os
import pickle
import json

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

# ================= LOAD MODEL =================
def load_model():
    try:
        with open("model.pkl", "rb") as f:
            return pickle.load(f)
    except:
        return None

# ================= LOAD LIVE DATA =================
def load_live_data():
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

        if df.empty:
            return df

        # ================= CLEAN =================
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

        # ================= FEATURES =================
        df["budget_ratio"] = df["allocated_budget"] / df["total_budget"]
        df["cost_per_participant"] = df["allocated_budget"] / (df["participants"] + 1)
        df["implementation_strength"] = df["evaluation_score"] * (df["participants"] + 1)
        df["budget_utilization"] = ((df["total_budget"] - df["remaining_budget"]) / df["total_budget"]) * 100

        return df

    except Exception as e:
        print("DB ERROR:", e)
        return pd.DataFrame()

# ================= HOME =================
@app.route("/")
def home():
    return {"status": "Municipal Intelligence API Running"}

# ================= PREDICT =================
@app.route("/predict", methods=["GET"])
def predict():
    try:
        df = load_data()

        if df.empty:
            return jsonify({"error": "No data found"}), 400

        model = train_model(df)

        X = df[["participants", "budget", "efficiency", "quality"]]
        df["score"] = model.predict(X)

        results = []

        for i, row in df.iterrows():
            results.append({
                "title": f"Activity {i+1}",
                "participants": int(row["participants"]),
                "budget": float(row["budget"]),
                "predicted_score": float(row["score"]),
                "barangay_id": 1  # IMPORTANT FIX
            })

        # 🔥 SAVE FILE FOR PHP
        with open("../ml/ml_results.json", "w") as f:
            json.dump(results, f, indent=4)

        return jsonify({
            "status": "success",
            "saved_records": len(results)
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500
        

# ================= RUN =================

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port, debug=False)