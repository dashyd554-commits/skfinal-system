from flask import Flask, jsonify, request
import pandas as pd
import psycopg2
import os
from sklearn.ensemble import RandomForestRegressor

app = Flask(__name__)

def get_connection():
    return psycopg2.connect(
        host=os.getenv("DB_HOST"),
        database=os.getenv("DB_NAME"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        port=os.getenv("DB_PORT", "5432")
    )

def load_data(barangay_id=None):
    conn = get_connection()
    cur  = conn.cursor()

    # ── Step 1: find which budget column actually exists in activities ──
    cur.execute("""
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'activities'
        AND column_name IN (
            'budget_requested','allocated_budget',
            'budget_allocated','budget','amount'
        )
        LIMIT 1
    """)
    act_budget_col = cur.fetchone()

    # ── Step 2: find which participant column exists ──
    cur.execute("""
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'activities'
        AND column_name IN ('participants','target_participants','attendees')
        LIMIT 1
    """)
    act_part_col = cur.fetchone()

    df = pd.DataFrame()

    # ── Step 3: try activities if usable columns exist ──
    if act_budget_col and act_part_col:
        bc = act_budget_col[0]
        pc = act_part_col[0]
        bid_filter = "AND barangay_id = %s" if barangay_id else ""
        query = f"""
            SELECT
                COALESCE({pc}, 0)            AS participants,
                COALESCE({bc}, 1)            AS budget,
                COALESCE(evaluation_score, 50) AS evaluation_score
            FROM activities
            WHERE COALESCE({bc}, 0) > 0
            {bid_filter}
            LIMIT 500
        """
        params = [barangay_id] if barangay_id else []
        df = pd.read_sql_query(query, conn, params=params if params else None)

    # ── Step 4: fall back to projects if activities empty ──
    if df.empty or df['participants'].sum() == 0:
        bid_filter = "AND barangay_id = %s" if barangay_id else ""
        query = f"""
            SELECT
                COALESCE(target_participants, 10) AS participants,
                COALESCE(budget_requested, 1)     AS budget,
                CASE
                    WHEN status = 'approved' THEN 80
                    WHEN status = 'rejected' THEN 20
                    ELSE 50
                END                               AS evaluation_score
            FROM projects
            WHERE COALESCE(budget_requested, 0) > 0
            {bid_filter}
            LIMIT 500
        """
        params = [barangay_id] if barangay_id else []
        df = pd.read_sql_query(query, conn, params=params if params else None)

    cur.close()
    conn.close()

    if df.empty:
        return df

    df["participants"]     = pd.to_numeric(df["participants"],     errors="coerce").fillna(0)
    df["budget"]           = pd.to_numeric(df["budget"],           errors="coerce").fillna(1).replace(0, 1)
    df["evaluation_score"] = pd.to_numeric(df["evaluation_score"], errors="coerce").fillna(50)
    df["efficiency"]       = df["participants"] / df["budget"]
    df["quality"]          = df["evaluation_score"] / 100

    return df

def train_model(df):
    X = df[["participants", "budget", "efficiency", "quality"]]
    y = ((df["efficiency"] * 50) + (df["quality"] * 50)).clip(0, 100)
    model = RandomForestRegressor(n_estimators=100, random_state=42)
    model.fit(X, y)
    return model

@app.route("/")
def home():
    return jsonify({"status": "running", "message": "Flask ML API is working"})

@app.route("/predict")
def predict():
    try:
        barangay_id = request.args.get("barangay_id", None)
        if barangay_id:
            barangay_id = int(barangay_id)

        df = load_data(barangay_id)

        if df.empty:
            return jsonify({
                "status":     "error",
                "mean_score": 0,
                "row_count":  0,
                "message":    "No usable data found for this barangay in activities or projects."
            }), 400

        model  = train_model(df)
        X      = df[["participants", "budget", "efficiency", "quality"]]
        scores = model.predict(X).clip(0, 100)

        return jsonify({
            "status":      "ok",
            "mean_score":  round(float(scores.mean()), 2),
            "row_count":   len(df),
            "barangay_id": barangay_id,
            "source":      "activities" if df['participants'].sum() > 0 else "projects"
        })

    except Exception as e:
        return jsonify({
            "status":     "error",
            "message":    str(e),
            "mean_score": 0
        }), 500

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)