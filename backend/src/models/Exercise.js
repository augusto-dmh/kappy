import { Model, DataTypes } from "sequelize";
import ExerciseChoice from "./ExerciseChoice";

export default class Exercise extends Model {
  static init(sequelize) {
    super.init(
      {
        lessonId: {
          type: DataTypes.INTEGER,
          references: {
            model: "lessons",
            key: "id",
          },
        },
        question: {
          type: DataTypes.TEXT,
          allowNull: false,
        },
      },
      {
        sequelize,
        modelName: "exercise",
      }
    );

    this.addScope("defaultScope", {
      include: {
        model: ExerciseChoice,
        as: "choices",
        attributes: { exclude: ["createdAt", "updatedAt", "id", "exerciseId"] },
      },
      attributes: { exclude: ["createdAt", "updatedAt"] },
    });
  }

  static associate(models) {
    this.belongsTo(models.lesson, {
      foreignKey: "lessonId",
      as: "lesson",
    });
    this.hasMany(models.exerciseChoice, {
      foreignKey: "exerciseId",
      as: "choices",
    });
  }
}
