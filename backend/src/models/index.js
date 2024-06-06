import Avatar from "./Avatar";
import User from "./User";
import UserAvatar from "./UserAvatar";
import Lesson from "./Lesson";
import Exercise from "./Exercise";
import ExerciseChoice from "./ExerciseChoice";
import LessonDependency from "./LessonDependency";
import Module from "./Module";

export default function initModels(sequelize) {
  const models = [
    Avatar,
    User,
    UserAvatar,
    Module,
    Exercise,
    Lesson,
    ExerciseChoice,
    LessonDependency,
  ];

  models.forEach((model) => model.init(sequelize));
  models.forEach(
    (model) => model.associate && model.associate(sequelize.models)
  );
}
