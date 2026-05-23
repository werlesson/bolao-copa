export interface PhasePoints {
  stage: string
  label: string
  points: number
}

export interface ProfileStats {
  position: number | null
  total_points: number
  total_predictions: number
  exact_scores: number
  correct_results: number
  accuracy_percent: number | null
  phase_points: PhasePoints[]
}

export interface ProfilePrediction {
  points_earned: number | null
  match: {
    stage: string
    status: string
  }
}
