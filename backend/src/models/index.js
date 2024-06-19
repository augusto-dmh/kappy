import User from "./User";
import Lesson from "./Lesson";
import Exercise from "./Exercise";
import ExerciseChoice from "./ExerciseChoice";

export default function initModels(sequelize) {
  const models = [Lesson, User, Exercise, ExerciseChoice];

  models.forEach((model) => model.init(sequelize));
  models.forEach(
    (model) => model.associate && model.associate(sequelize.models)
  );
}
